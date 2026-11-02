<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_verifies_and_detects_tampering(): void
    {
        $user = $this->makeUser(Role::Finance);
        $audit = app(AuditLogger::class);
        $audit->log('test.one', $user, ['a' => 1], ['a' => 2, 'nested' => ['z' => 1, 'b' => 'x']]);
        $audit->log('test.two', $user, null, ['password' => 'secret', 'ok' => true], 'dlil');

        $this->assertTrue($audit->verify()['ok']);
        $this->assertArrayNotHasKey('password', AuditEvent::where('action', 'test.two')->first()->after);

        $target = AuditEvent::where('action', 'test.one')->first();
        DB::table('audit_events')->where('id', $target->id)->update(['after' => json_encode(['a' => 999])]);

        $result = $audit->verify();
        $this->assertFalse($result['ok']);
        $this->assertSame($target->id, $result['broken_at']);
    }

    public function test_events_are_immutable_through_the_model(): void
    {
        $this->bootstrapTenant();
        $event = AuditEvent::first();

        $this->expectException(LogicException::class);
        $event->update(['action' => 'hacked']);
    }

    public function test_deleting_a_row_breaks_the_chain(): void
    {
        $this->bootstrapTenant();
        $audit = app(AuditLogger::class);
        $audit->log('a');
        $audit->log('b');
        $audit->log('c');
        DB::table('audit_events')->where('action', 'b')->delete();

        $this->assertFalse($audit->verify()['ok']);
    }
}
