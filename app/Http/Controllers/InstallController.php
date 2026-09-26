<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Madnest\Madzipper\Facades\Madzipper;
use Illuminate\Support\Facades\Session;

class InstallController extends Controller
{

    public function step0()
    {
        return view('installation.step0');
    }

    public function step1(Request $request)
    {
        if (Hash::check('step_1', $request['token'])) {
            $permission['curl_enabled'] = function_exists('curl_version');
            //extensions
            $permission['curl'] = function_exists('curl_version');
            $permission['bcmath'] = extension_loaded('bcmath');
            $permission['ctype'] = extension_loaded('ctype');
            $permission['json'] = extension_loaded('json');
            $permission['mbstring'] = extension_loaded('mbstring');
            $permission['openssl'] = extension_loaded('openssl');
            $permission['pdo'] = defined('PDO::ATTR_DRIVER_NAME');
            $permission['tokenizer'] = extension_loaded('tokenizer');
            $permission['xml'] = extension_loaded('xml');
            $permission['zip'] = extension_loaded('zip');
            $permission['fileinfo'] = extension_loaded('fileinfo');
            $permission['gd'] = extension_loaded('gd');
            $permission['sodium'] = extension_loaded('sodium');
            $permission['pdo_mysql'] = extension_loaded('pdo_mysql');
            $permission['db_file_write_perm'] = is_writable(base_path('.env'));
            $permission['routes_file_write_perm'] = is_writable(base_path('app/Providers/RouteServiceProvider.php'));
            return view('installation.step1', compact('permission'));
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step2(Request $request)
    {
        if (Hash::check('step_2', $request['token'])) {
            return view('installation.step2');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step3(Request $request)
    {
        if (Hash::check('step_3', $request['token'])) {
            return view('installation.step3');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step4(Request $request)
    {
        if (Hash::check('step_4', $request['token'])) {
            return view('installation.step4');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step5(Request $request)
    {
        if (Hash::check('step_5', $request['token'])) {
            return view('installation.step5');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function mili_setup(Request $request)
    {
        $request->validate([
            'app_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9 _-]+$/'],
        ]);

        Session::put('mili_install_ready', true);
        Session::put('mili_app_name', $request->input('app_name', 'Mili'));

        return redirect('step3?token='.bcrypt('step_3'));
    }

    public function system_settings(Request $request)
    {
        if (!Hash::check('step_6', $request['token'])) {
            session()->flash('error', 'Access denied!');
            return redirect()->route('step0');
        }

        DB::table('admins')->insertOrIgnore([
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'email' => $request['email'],
            'role_id' => 1,
            'password' => bcrypt($request['password']),
            'phone' => $request['phone'],
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('business_settings')->where(['key' => 'business_name'])->update([
            'value' => $request['business_name']
        ]);

        Helpers::insert_business_settings_key('system_language','[{"id":1,"direction":"ltr","code":"en","status":1,"default":true}]');

        //version 2.2.0
        Helpers::insert_data_settings_key('admin_login_url', 'login_admin' ,'admin');
        Helpers::insert_data_settings_key('admin_employee_login_url', 'login_admin_employee' ,'admin-employee');
        Helpers::insert_data_settings_key('store_login_url', 'login_store' ,'vendor');
        Helpers::insert_data_settings_key('store_employee_login_url', 'login_store_employee' ,'vendor-employee');

        Helpers::insert_business_settings_key('check_daily_subscription_validity_check', date('Y-m-d'));

        Helpers::insert_business_settings_key('country_picker_status', '1');
        Helpers::insert_business_settings_key('manual_login_status', '1');


        $previousRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.php');
        $newRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.txt');
        copy($newRouteServiceProvier, $previousRouteServiceProvier);

        Helpers::remove_dir('storage/app/public');
        Storage::disk('public')->makeDirectory('/');

        try {
            Madzipper::make('installation/backup/public.zip')->extractTo('storage/app');
        }catch (\Exception $exception){
            info($exception);
        }

        //sleep(5);
        return view('installation.step6');
    }

    public function database_installation(Request $request)
    {
        if (self::check_database_connection($request->DB_HOST, $request->DB_DATABASE, $request->DB_USERNAME, $request->DB_PASSWORD)) {

            $key = base64_encode(random_bytes(32));
            $output = 'APP_NAME="' . session('mili_app_name', 'Mili') . '"' . "\n" .
                    'APP_ENV=live
                    APP_KEY=base64:' . $key . '
                    APP_DEBUG=false
                    APP_INSTALL=true
                    APP_LOG_LEVEL=debug
                    APP_MODE=live
                    APP_URL=' . URL::to('/') . '

                    DB_CONNECTION=mysql
                    DB_HOST=' . $request->DB_HOST . '
                    DB_PORT=3306
                    DB_DATABASE=' . $request->DB_DATABASE . '
                    DB_USERNAME=' . $request->DB_USERNAME . '
                    DB_PASSWORD="' . $request->DB_PASSWORD . '"

                    BROADCAST_DRIVER=log
                    CACHE_DRIVER=database
                    SESSION_DRIVER=file
                    SESSION_LIFETIME=120
                    QUEUE_DRIVER=sync

                    REDIS_HOST=127.0.0.1
                    REDIS_PASSWORD=null
                    REDIS_PORT=6379

                    PUSHER_APP_ID=
                    PUSHER_APP_KEY=
                    PUSHER_APP_SECRET=
                    PUSHER_APP_CLUSTER=mt1

                    MILI_INSTALL=true
                    MILI_ENV=production

                    SOFTWARE_VERSION=3.8
                    ';
            $file = fopen(base_path('.env'), 'w');
            fwrite($file, $output);
            fclose($file);

            $path = base_path('.env');
            if (file_exists($path)) {
                return redirect()->route('step4', ['token' => $request['token']]);
            } else {
                session()->flash('error', 'Database error!');
                return redirect()->route('step3', ['token' => bcrypt('step_3')]);
            }
        } else {
            session()->flash('error', 'Database host error!');
            return redirect()->route('step3', ['token' => bcrypt('step_3')]);
        }
    }

    public function import_sql()
    {
        try {
            $sql_path = base_path('installation/backup/database.sql');
            DB::unprepared(file_get_contents($sql_path));
            // version_2.11.1
            Artisan::call('cache:table');
            return redirect()->route('step5', ['token' => bcrypt('step_5')]);
        } catch (\Exception $exception) {
            session()->flash('error', 'Your database is not clean, do you want to clean database then import?');
            return back();
        }
    }

    public function force_import_sql()
    {
        try {
            Artisan::call('db:wipe', ['--force' => true]);
            $sql_path = base_path('installation/backup/database.sql');
            DB::unprepared(file_get_contents($sql_path));
            // version_2.11.1
            Artisan::call('cache:table');
            return redirect()->route('step5', ['token' => bcrypt('step_5')]);
        } catch (\Exception $exception) {
            session()->flash('error', 'Check your database permission!');
            return back();
        }
    }

    function check_database_connection($db_host = "", $db_name = "", $db_user = "", $db_pass = ""): bool
    {
        try {
            if (@mysqli_connect($db_host, $db_user, $db_pass, $db_name)) {
                return true;
            } else {
                return false;
            }
        }catch(\Exception $exception){
            return false;
        }
    }
}
