<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

if (!function_exists('translate')) {
    function translate($key): string
    {
        $local = getDefaultLanguage();
        App::setLocale($local);

        try {
            $key = preg_replace('/^messages\./i', '', trim($key));
            $lang_path = base_path('resources/lang/' . $local . '/messages.php');
            $lang_array = file_exists($lang_path) ? include($lang_path) : [];
            $key = removeSpecialCharacters($key);
            $processed_key = ucfirst(trim(str_replace('_', ' ', $key)));

            if (!array_key_exists($key, $lang_array)) {
                $result = $processed_key;

                $lang_directory = dirname($lang_path);
                if (is_array($lang_array) && file_exists($lang_directory) && (is_writable($lang_path) || is_writable($lang_directory))) {
                    $lang_array[$key] = $processed_key;
                    $str = "<?php return " . var_export($lang_array, true) . ";";
                    file_put_contents($lang_path, $str);
                }
            } else {
                $result = __('messages.' . $key);
            }
        } catch (\Exception $exception) {
            $result = __('messages.' . $key);
        }

        return $result;
    }
}


if (!function_exists('removeSpecialCharacters')) {

    function removeSpecialCharacters(string $text): string
    {
        return str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', preg_replace('/\s\s+/', ' ', $text));
    }
}

if (!function_exists('getDefaultLanguage')) {
    function getDefaultLanguage(): string
    {
        if (strpos(url()->current(), '/api')) {
            $lang = App::getLocale();
        } elseif (session()->has('local')) {
            $lang = session('local');
        } else {
            $data = getWebConfig('language');
            $code = 'en';
            $direction = 'ltr';
            foreach ($data as $ln) {
                if (array_key_exists('default', $ln) && $ln['default']) {
                    $code = $ln['code'];
                    if (array_key_exists('direction', $ln)) {
                        $direction = $ln['direction'];
                    }
                }
            }
            session()->put('local', $code);
            Session::put('direction', $direction);
            $lang = $code;
        }
        return $lang;
    }
}
