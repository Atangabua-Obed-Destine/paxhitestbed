<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('languages')->delete();

        $languages = Language::create([

            'name'=>'English',
            'code'=>'en',
            'direction'=>'0',
            'default'=>'1',
            'status'=>'1',

        ]);
    }
}
