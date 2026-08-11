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

    'accepted' => ':attribute უნდა იყოს მიღებული.',
    'active_url' => ':attribute არ არის სწორი URL.',
    'after' => ':attribute უნდა იყოს :date-ის შემდეგი თარიღი.',
    'after_or_equal' => ':attribute უნდა იყოს :date-ის ტოლი ან შემდეგი თარიღი.',
    'alpha' => ':attribute უნდა შეიცავდეს მხოლოდ ასოებს.',
    'alpha_dash' => ':attribute უნდა შეიცავდეს მხოლოდ ასოებს, ციფრებს, დეფისებსა და ქვედა ტირეებს.',
    'alpha_num' => ':attribute უნდა შეიცავდეს მხოლოდ ასოებსა და ციფრებს.',
    'array' => ':attribute უნდა იყოს მასივი.',
    'before' => ':attribute უნდა იყოს :date-მდე არსებული თარიღი.',
    'before_or_equal' => ':attribute უნდა იყოს :date-ის ტოლი ან წინა თარიღი.',
    'between' => [
        'numeric' => ':attribute უნდა იყოს :min-სა და :max-ს შორის.',
        'file' => ':attribute-ის ზომა უნდა იყოს :min-დან :max კილობაიტამდე.',
        'string' => ':attribute უნდა შეიცავდეს :min-დან :max სიმბოლომდე.',
        'array' => ':attribute უნდა შეიცავდეს :min-დან :max ელემენტამდე.',
    ],
    'boolean' => ':attribute ველი უნდა იყოს true ან false.',
    'confirmed' => ':attribute-ის დადასტურება არ ემთხვევა.',
    'date' => ':attribute არ არის სწორი თარიღი.',
    'date_equals' => ':attribute უნდა იყოს :date-ის ტოლი თარიღი.',
    'date_format' => ':attribute არ შეესაბამება :format ფორმატს.',
    'different' => ':attribute და :other ერთმანეთისგან განსხვავებული უნდა იყოს.',
    'digits' => ':attribute უნდა შედგებოდეს :digits ციფრისგან.',
    'digits_between' => ':attribute უნდა შედგებოდეს :min-დან :max ციფრამდე.',
    'dimensions' => ':attribute სურათის ზომები არასწორია.',
    'distinct' => ':attribute ველში განმეორებითი მნიშვნელობაა.',
    'email' => ':attribute უნდა იყოს სწორი ელფოსტის მისამართი.',
    'ends_with' => ':attribute უნდა მთავრდებოდეს ერთ-ერთი შემდეგი მნიშვნელობით: :values.',
    'exists' => 'არჩეული :attribute არასწორია.',
    'file' => ':attribute უნდა იყოს ფაილი.',
    'filled' => ':attribute ველი შევსებული უნდა იყოს.',
    'gt' => [
        'numeric' => ':attribute უნდა იყოს :value-ზე მეტი.',
        'file' => ':attribute-ის ზომა უნდა აღემატებოდეს :value კილობაიტს.',
        'string' => ':attribute უნდა შეიცავდეს :value-ზე მეტ სიმბოლოს.',
        'array' => ':attribute უნდა შეიცავდეს :value-ზე მეტ ელემენტს.',
    ],
    'gte' => [
        'numeric' => ':attribute უნდა იყოს :value-ის ტოლი ან მეტი.',
        'file' => ':attribute-ის ზომა უნდა იყოს მინიმუმ :value კილობაიტი.',
        'string' => ':attribute უნდა შეიცავდეს მინიმუმ :value სიმბოლოს.',
        'array' => ':attribute უნდა შეიცავდეს მინიმუმ :value ელემენტს.',
    ],
    'image' => ':attribute უნდა იყოს სურათი.',
    'in' => 'არჩეული :attribute არასწორია.',
    'in_array' => ':attribute ველი :other-ში არ არსებობს.',
    'integer' => ':attribute უნდა იყოს მთელი რიცხვი.',
    'ip' => ':attribute უნდა იყოს სწორი IP მისამართი.',
    'ipv4' => ':attribute უნდა იყოს სწორი IPv4 მისამართი.',
    'ipv6' => ':attribute უნდა იყოს სწორი IPv6 მისამართი.',
    'json' => ':attribute უნდა იყოს სწორი JSON სტრიქონი.',
    'lt' => [
        'numeric' => ':attribute უნდა იყოს :value-ზე ნაკლები.',
        'file' => ':attribute-ის ზომა უნდა იყოს :value კილობაიტზე ნაკლები.',
        'string' => ':attribute უნდა შეიცავდეს :value-ზე ნაკლებ სიმბოლოს.',
        'array' => ':attribute უნდა შეიცავდეს :value-ზე ნაკლებ ელემენტს.',
    ],
    'lte' => [
        'numeric' => ':attribute უნდა იყოს :value-ის ტოლი ან ნაკლები.',
        'file' => ':attribute-ის ზომა არ უნდა აღემატებოდეს :value კილობაიტს.',
        'string' => ':attribute არ უნდა შეიცავდეს :value-ზე მეტ სიმბოლოს.',
        'array' => ':attribute არ უნდა შეიცავდეს :value-ზე მეტ ელემენტს.',
    ],
    'max' => [
        'numeric' => ':attribute არ უნდა იყოს :max-ზე მეტი.',
        'file' => ':attribute არ უნდა იყოს :max კილობაიტზე მეტი.',
        'string' => ':attribute არ უნდა შეიცავდეს :max-ზე მეტ სიმბოლოს.',
        'array' => ':attribute არ უნდა შეიცავდეს :max-ზე მეტ ელემენტს.',
    ],
    'mimes' => ':attribute უნდა იყოს შემდეგი ტიპის ფაილი: :values.',
    'mimetypes' => ':attribute უნდა იყოს შემდეგი ტიპის ფაილი: :values.',
    'min' => [
        'numeric' => ':attribute უნდა იყოს მინიმუმ :min.',
        'file' => ':attribute-ის ზომა უნდა იყოს მინიმუმ :min კილობაიტი.',
        'string' => ':attribute უნდა შეიცავდეს მინიმუმ :min სიმბოლოს.',
        'array' => ':attribute უნდა შეიცავდეს მინიმუმ :min ელემენტს.',
    ],
    'multiple_of' => ':attribute უნდა იყოს :value-ის ჯერადი.',
    'not_in' => 'არჩეული :attribute არასწორია.',
    'not_regex' => ':attribute-ის ფორმატი არასწორია.',
    'numeric' => ':attribute უნდა იყოს რიცხვი.',
    'password' => 'პაროლი არასწორია.',
    'present' => ':attribute ველი წარმოდგენილი უნდა იყოს.',
    'regex' => ':attribute-ის ფორმატი არასწორია.',
    'required' => ':attribute ველი სავალდებულოა.',
    'required_if' => ':attribute ველი სავალდებულოა, როდესაც :other არის :value.',
    'required_unless' => ':attribute ველი სავალდებულოა, თუ :other არ არის :values-ში.',
    'required_with' => ':attribute ველი სავალდებულოა, როდესაც :values მითითებულია.',
    'required_with_all' => ':attribute ველი სავალდებულოა, როდესაც მითითებულია ყველა შემდეგი ველი: :values.',
    'required_without' => ':attribute ველი სავალდებულოა, როდესაც :values მითითებული არ არის.',
    'required_without_all' => ':attribute ველი სავალდებულოა, როდესაც არცერთი :values არ არის მითითებული.',
    'prohibited' => ':attribute ველის გამოყენება აკრძალულია.',
    'prohibited_if' => ':attribute ველის გამოყენება აკრძალულია, როცა :other არის :value.',
    'prohibited_unless' => ':attribute ველის გამოყენება აკრძალულია, თუ :other არ შედის :values-ში.',
    'same' => ':attribute და :other ერთმანეთს უნდა ემთხვეოდეს.',
    'size' => [
        'numeric' => ':attribute უნდა იყოს :size.',
        'file' => ':attribute-ის ზომა უნდა იყოს :size კილობაიტი.',
        'string' => ':attribute უნდა შეიცავდეს :size სიმბოლოს.',
        'array' => ':attribute უნდა შეიცავდეს :size ელემენტს.',
    ],
    'starts_with' => ':attribute უნდა იწყებოდეს ერთ-ერთი შემდეგი მნიშვნელობით: :values.',
    'string' => ':attribute უნდა იყოს ტექსტური მნიშვნელობა.',
    'timezone' => ':attribute უნდა იყოს სწორი დროის სარტყელი.',
    'unique' => ':attribute უკვე გამოყენებულია.',
    'uploaded' => ':attribute-ის ატვირთვა ვერ მოხერხდა.',
    'url' => ':attribute-ის ფორმატი არასწორია.',
    'uuid' => ':attribute უნდა იყოს სწორი UUID.',

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
            'rule-name' => 'მორგებული შეტყობინება',
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

    'attributes' => [
        'type' => 'ტიპი',
        'module_type' => 'მოდულის ტიპი',
        'delivery_time_type' => 'მიტანის დროის ტიპი',
        'coupon_type' => 'კუპონის ტიპი',
        'order_type' => 'შეკვეთის ტიპი',
        'login_type' => 'ავტორიზაციის ტიპი',
        'cover_photo' => 'გარეკანის ფოტო',
        'logo' => 'ლოგო',
        'image' => 'სურათი',
        'identity_type' => 'პირადობის დამადასტურებელი დოკუმენტის ტიპი',
        'identity_number' => 'პირადობის დოკუმენტის ნომერი',
        'vehicle_id' => 'სატრანსპორტო საშუალება',
        'earning' => 'შემოსავალი',
        'f_name' => 'სახელი',
        'l_name' => 'გვარი',
        'name' => 'სახელი',
        'email' => 'ელფოსტა',
        'payment_method' => 'გადახდის მეთოდი',
        'address_type' => 'მისამართის ტიპი',
        'phone' => 'ტელეფონის ნომერი',
        'password' => 'პაროლი',
        'zone_id' => 'ზონა',
        'module_id' => 'სერვისი',
        'address' => 'მისამართი',
        'latitude' => 'განედი',
        'longitude' => 'გრძედი',
        'tax' => 'გადასახადი',
        'tin' => 'საიდენტიფიკაციო ნომერი',
        'tin_certificate_image' => 'საიდენტიფიკაციო სერტიფიკატის სურათი',
    ],

];
