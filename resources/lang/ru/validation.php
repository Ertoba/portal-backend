<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Вы должны принять :attribute.',
    'active_url' => 'Значение поля :attribute не является действительным URL.',
    'after' => 'Значение поля :attribute должно быть датой после :date.',
    'after_or_equal' => 'Значение поля :attribute должно быть датой после или равной :date.',
    'alpha' => 'Значение поля :attribute может содержать только буквы.',
    'alpha_dash' => 'Значение поля :attribute может содержать только буквы, цифры, дефис и нижнее подчеркивание.',
    'alpha_num' => 'Значение поля :attribute может содержать только буквы и цифры.',
    'array' => 'Значение поля :attribute должно быть массивом.',
    'before' => 'Значение поля :attribute должно быть датой до :date.',
    'before_or_equal' => 'Значение поля :attribute должно быть датой до или равной :date.',
    'between' => [
        'numeric' => 'Значение поля :attribute должно быть между :min и :max.',
        'file' => 'Размер файла в поле :attribute должен быть между :min и :max Кб.',
        'string' => 'Количество символов в поле :attribute должно быть между :min и :max.',
        'array' => 'Количество элементов в поле :attribute должно быть между :min и :max.',
    ],
    'boolean' => 'Значение поля :attribute должно быть логического типа.',
    'confirmed' => 'Значение поля :attribute не совпадает с подтверждаемым.',
    'date' => 'Значение поля :attribute не является датой.',
    'date_equals' => 'Значение поля :attribute должно быть датой равной :date.',
    'date_format' => 'Значение поля :attribute не соответствует формату даты :format.',
    'different' => 'Значения полей :attribute и :other должны различаться.',
    'digits' => 'Количество символов в поле :attribute должно быть равным :digits.',
    'digits_between' => 'Количество символов в поле :attribute должно быть между :min и :max.',
    'dimensions' => 'Изображение, указанное в поле :attribute, имеет недопустимые размеры.',
    'distinct' => 'Значения поля :attribute не должны повторяться.',
    'email' => 'Значение поля :attribute должно быть действительным электронным адресом.',
    'ends_with' => 'Значение поля :attribute должно заканчиваться одним из следующих: :values',
    'exists' => 'Значение поля :attribute не существует.',
    'file' => 'В поле :attribute должен быть указан файл.',
    'filled' => 'Значение поля :attribute обязательно для заполнения.',
    'gt' => [
        'numeric' => 'Значение поля :attribute должно быть больше :value.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть больше :value Кб.',
        'string' => 'Количество символов в поле :attribute должно быть больше :value.',
        'array' => 'Количество элементов в поле :attribute должно быть больше :value.',
    ],
    'gte' => [
        'numeric' => 'Значение поля :attribute должно быть :value или больше.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть :value Кб или больше.',
        'string' => 'Количество символов в поле :attribute должно быть :value или больше.',
        'array' => 'Количество элементов в поле :attribute должно быть :value или больше.',
    ],
    'image' => 'Файл, указанный в поле :attribute, должен быть изображением.',
    'in' => 'Значение поля :attribute некорректно.',
    'in_array' => 'Значение поля :attribute не существует в :other.',
    'integer' => 'Значение поля :attribute должно быть целым числом.',
    'ip' => 'Значение поля :attribute должно быть действительным IP-адресом.',
    'ipv4' => 'Значение поля :attribute должно быть действительным IPv4-адресом.',
    'ipv6' => 'Значение поля :attribute должно быть действительным IPv6-адресом.',
    'json' => 'Значение поля :attribute должно быть JSON строкой.',
    'lt' => [
        'numeric' => 'Значение поля :attribute должно быть меньше :value.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть меньше :value Кб.',
        'string' => 'Количество символов в поле :attribute должно быть меньше :value.',
        'array' => 'Количество элементов в поле :attribute должно быть меньше :value.',
    ],
    'lte' => [
        'numeric' => 'Значение поля :attribute должно быть равным или меньше :value.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть :value Кб или меньше.',
        'string' => 'Количество символов в поле :attribute должно быть :value или меньше.',
        'array' => 'Количество элементов в поле :attribute должно быть :value или меньше.',
    ],
    'max' => [
        'numeric' => 'Значение поля :attribute не может быть больше :max.',
        'file' => 'Размер файла в поле :attribute не может быть больше :max Кб.',
        'string' => 'Количество символов в значении поля :attribute не может превышать :max.',
        'array' => 'Количество элементов в поле :attribute не может превышать :max.',
    ],
    'mimes' => 'Файл, указанный в поле :attribute, должен быть одного из следующих типов: :values.',
    'mimetypes' => 'Файл, указанный в поле :attribute, должен быть одного из следующих типов: :values.',
    'min' => [
        'numeric' => 'Значение поля :attribute должно быть не меньше :min.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть не меньше :min Кб.',
        'string' => 'Количество символов в поле :attribute должно быть не меньше :min.',
        'array' => 'Количество элементов в поле :attribute должно быть не меньше :min.',
    ],
    'multiple_of' => 'Значение поля :attribute должно быть кратным :value',
    'not_in' => 'Значение поля :attribute некорректно.',
    'not_regex' => 'Значение поля :attribute имеет некорректный формат.',
    'numeric' => 'Значение поля :attribute должно быть числом.',
    'password' => 'Некорректный пароль.',
    'present' => 'Значение поля :attribute должно быть.',
    'regex' => 'Значение поля :attribute имеет некорректный формат.',
    'required' => 'Поле :attribute обязательно.',
    'required_if' => 'Поле :attribute обязательно для заполнения, когда :other равно :value.',
    'required_unless' => 'Поле :attribute обязательно для заполнения, когда :other не равно :values.',
    'required_with' => 'Поле :attribute обязательно для заполнения, когда :values указано.',
    'required_with_all' => 'Поле :attribute обязательно для заполнения, когда :values указано.',
    'required_without' => 'Поле :attribute обязательно для заполнения, когда :values не указано.',
    'required_without_all' => 'Поле :attribute обязательно для заполнения, когда ни одно из :values не указано.',
    'prohibited' => 'Значение поля :attribute запрещено.',
    'prohibited_if' => 'Значение поля :attribute запрещено, когда :other равно :value.',
    'prohibited_unless' => 'Значение поля :attribute запрещено, если :other не состоит в :values.',
    'same' => 'Значения полей :attribute и :other должны совпадать.',
    'size' => [
        'numeric' => 'Значение поля :attribute должно быть равным :size.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть равен :size Кб.',
        'string' => 'Количество символов в поле :attribute должно быть равным :size.',
        'array' => 'Количество элементов в поле :attribute должно быть равным :size.',
    ],
    'starts_with' => 'Поле :attribute должно начинаться с одного из следующих значений: :values',
    'string' => 'Значение поля :attribute должно быть строкой.',
    'timezone' => 'Значение поля :attribute должно быть действительным часовым поясом.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'uploaded' => 'Загрузка файла из поля :attribute не удалась.',
    'url' => 'Значение поля :attribute имеет ошибочный формат URL.',
    'uuid' => 'Значение поля :attribute должно быть корректным UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
