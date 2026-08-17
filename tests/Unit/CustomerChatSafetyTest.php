<?php

namespace Tests\Unit;

use Modules\AI\app\Http\Controllers\Api\V1\CustomerChatController;
use Modules\AI\app\Services\CustomerChatReadOnlyTools;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CustomerChatSafetyTest extends TestCase
{
    public function test_customer_tools_are_an_explicit_read_only_allow_list(): void
    {
        $tools = new CustomerChatReadOnlyTools(1, [1]);
        $names = array_map(
            fn (array $definition): string => $definition['function']['name'],
            $tools->definitions()
        );

        $this->assertSame([
            'search_products',
            'get_popular_items',
            'get_best_deals',
            'search_stores',
            'get_store_details',
            'get_categories',
            'get_platform_info',
            'get_supported_languages',
        ], $names);
        $this->assertSame(
            [],
            preg_grep('/add|create|update|delete|remove|cart|order|payment|refund/i', $names)
        );
    }

    /** @dataProvider languageMessages */
    public function test_latest_message_controls_the_reply_language(
        string $message,
        string $fallback,
        string $expected
    ): void {
        $method = new ReflectionMethod(CustomerChatController::class, 'resolveReplyLanguage');
        $method->setAccessible(true);

        $this->assertSame(
            $expected,
            $method->invoke(new CustomerChatController(), $message, $fallback)
        );
    }

    public static function languageMessages(): array
    {
        return [
            'Georgian script' => ['სად არის ჩემი შეკვეთა?', 'English', 'Georgian'],
            'Russian script' => ['Где мой заказ?', 'English', 'Russian'],
            'English script' => ['Where is my order?', 'Georgian', 'English'],
            'Explicit Georgian' => ['Please answer ქართულად', 'English', 'Georgian'],
            'Explicit Russian' => ['Please answer по-русски', 'English', 'Russian'],
            'Explicit English' => ['პასუხი in English', 'Georgian', 'English'],
        ];
    }
}
