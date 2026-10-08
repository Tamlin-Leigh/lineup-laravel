<?php

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('enums live in App\Enums and are backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');
