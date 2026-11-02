<?php

namespace Database\Seeders;

use App\Services\InitialSetup;
use Illuminate\Database\Seeder;

/** Local development only. Production is set up through public/install.php. */
class DatabaseSeeder extends Seeder
{
    public function run(InitialSetup $setup): void
    {
        $setup->run('شرکت صنایع الکترونیک فاران', 'admin', 'مدیر سامانه', 'admin-dev-password');
    }
}
