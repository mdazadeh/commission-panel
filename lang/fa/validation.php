<?php

// Persian validation messages for the rules used in this app.
return [
    'accepted' => ':attribute باید پذیرفته شود.',
    'boolean' => ':attribute باید درست یا نادرست باشد.',
    'confirmed' => 'تکرار :attribute مطابقت ندارد.',
    'date' => ':attribute تاریخ معتبر نیست.',
    'email' => ':attribute باید ایمیل معتبر باشد.',
    'exists' => ':attribute انتخاب‌شده معتبر نیست.',
    'file' => ':attribute باید فایل باشد.',
    'in' => ':attribute انتخاب‌شده معتبر نیست.',
    'integer' => ':attribute باید عدد صحیح باشد.',
    'max' => [
        'file' => ':attribute نباید بیشتر از :max کیلوبایت باشد.',
        'numeric' => ':attribute نباید بیشتر از :max باشد.',
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'mimes' => ':attribute باید از نوع :values باشد.',
    'min' => [
        'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => ':attribute باید حداقل :min باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'numeric' => ':attribute باید عدد باشد.',
    'regex' => 'قالب :attribute معتبر نیست.',
    'required' => ':attribute الزامی است.',
    'string' => ':attribute باید متن باشد.',
    'unique' => 'این :attribute قبلاً استفاده شده است.',
    'uploaded' => 'بارگذاری :attribute ناموفق بود.',
    'enum' => ':attribute انتخاب‌شده معتبر نیست.',

    'password' => [
        'letters' => ':attribute باید حداقل یک حرف داشته باشد.',
        'mixed' => ':attribute باید حروف بزرگ و کوچک داشته باشد.',
        'numbers' => ':attribute باید حداقل یک عدد داشته باشد.',
        'symbols' => ':attribute باید حداقل یک نماد داشته باشد.',
        'uncompromised' => ':attribute در نشت‌های اطلاعاتی دیده شده است؛ رمز دیگری انتخاب کنید.',
    ],

    'attributes' => [
        'username' => 'نام کاربری',
        'password' => 'رمز عبور',
        'name' => 'نام',
        'email' => 'ایمیل',
        'role' => 'نقش',
        'team_id' => 'تیم',
        'marketer_code' => 'کد بازاریاب',
        'sales_started_on' => 'تاریخ شروع فروش',
        'is_active' => 'وضعیت',
        'file' => 'فایل',
    ],
];
