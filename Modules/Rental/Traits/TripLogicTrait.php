<?php

namespace Modules\Rental\Traits;

use App\Services\Payment\LoyaltyPointTransactionService;
use App\Services\Payment\WalletTransactionService;
use App\Services\Marketing\CashBackService;
use App\Models\User;
use App\Models\Admin;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\AdminWallet;
use App\Models\StoreWallet;
use App\CentralLogics\Helpers;
use App\Services\Marketing\CouponService;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Entities\PartialPayment;
use Modules\Rental\Entities\TripTransaction;
use App\Services\Order\OrderPaymentService;
use App\Services\Payment\AccountTransactionService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\Customer\UserService;
use Illuminate\Support\Facades\Log;


trait TripLogicTrait
{

    public static function create_transaction($trip, $received_by = false, $status = null)
    {
        $amount_admin = 0;
        $store_d_amount = 0;
        $admin_coupon_discount_subsidy = 0;
        $store_coupon_discount_subsidy = 0;
        $discount_on_trip = 0;
        $comission_on_store_amount = 0;
        $ref_bonus_amount = 0;
        $pro_discount_amount = 0;


        $provider = $trip?->provider;
        $store_sub = $provider?->store_sub;
        DB::beginTransaction();
        if ($trip->coupon_discount_by == 'admin') {
            $admin_coupon_discount_subsidy = $trip->coupon_discount_amount;
            self::tripExpenseCreate(amount: $admin_coupon_discount_subsidy, type: 'coupon_discount', datetime: now(), created_by: $trip->coupon_discount_by, trip_id: $trip->id);
        }
        if ($trip->ref_bonus_amount > 0) {
            $ref_bonus_amount = $trip->ref_bonus_amount;
            self::tripExpenseCreate(amount: $ref_bonus_amount, type: 'referral_discount', datetime: now(), created_by: 'admin', trip_id: $trip->id);
        }
        if ($trip->coupon_discount_by == 'vendor') {
            $store_coupon_discount_subsidy = $trip->coupon_discount_amount;
            self::tripExpenseCreate(amount: $store_coupon_discount_subsidy, type: 'coupon_discount', datetime: now(), created_by: $trip->coupon_discount_by, trip_id: $trip->id, store_id: $provider->id);
        }

        if ($trip?->cashback_history) {
            self::cashbackToWallet($trip);
        }

        $comission =   $provider?->comission ??  Helpers::get_business_settings('admin_commission', false);


        if ($trip->discount_on_trip > 0  && $trip->discount_on_trip_by == 'vendor') {
            if ($provider->store_business_model == 'subscription' && isset($store_sub)) {
                $store_d_amount =  $trip->discount_on_trip;
                self::tripExpenseCreate(amount: $store_d_amount, type: 'discount_on_trip', datetime: now(), created_by: 'vendor', trip_id: $trip->id, store_id: $trip->provider->id);
            } else {
                $amount_admin = $comission ? ($trip->discount_on_trip / 100) * $comission : 0;
                $store_d_amount =  $trip->discount_on_trip - $amount_admin;
                self::tripExpenseCreate(amount: $store_d_amount, type: 'discount_on_trip', datetime: now(), created_by: 'vendor', trip_id: $trip->id, store_id: $trip->provider->id);
                self::tripExpenseCreate(amount: $amount_admin, type: 'discount_on_trip', datetime: now(), created_by: 'admin', trip_id: $trip->id);
            }
        }

        if ($trip->discount_on_trip > 0  && $trip->discount_on_trip_by == 'admin') {
            $discount_on_trip = $trip->discount_on_trip;
            self::tripExpenseCreate(amount: $discount_on_trip, type: 'discount_on_trip', datetime: now(), created_by: 'admin', trip_id: $trip->id);
        }

        $pro_discount_amount = (float) ($trip->proDiscount?->amount_saved ?? 0);
        if ($pro_discount_amount > 0) {
            self::tripExpenseCreate(amount: $pro_discount_amount, type: 'pro_discount', datetime: now(), created_by: 'admin', trip_id: $trip->id);
        }



        $trip_amount = $trip->trip_amount - $trip->additional_charge  -  $trip->tax_amount   + $trip->coupon_discount_amount + $discount_on_trip  + $ref_bonus_amount + $store_d_amount + $amount_admin + $pro_discount_amount;


        if ($provider->store_business_model == 'subscription' && isset($store_sub)) {
            $comission_on_store_amount = 0;
            $subscription_mode = 1;
            $commission_percentage = 0;
        } else {
            $comission_on_store_amount = ($comission ? ($trip_amount / 100) * $comission : 0);
            $subscription_mode = 0;
            $commission_percentage = $comission;
        }

        $comission_amount = $comission_on_store_amount;

        $store_amount = $trip_amount + $trip->tax_amount  - $comission_on_store_amount - $store_coupon_discount_subsidy - $store_d_amount;
        try {
            TripTransaction::insert([
                'vendor_id' => $provider->vendor->id,
                'provider_id' => $provider->id,
                'trip_id' => $trip->id,
                'trip_amount' => $trip->trip_amount,
                'store_amount' => $store_amount,
                'admin_commission' => $comission_amount,
                'admin_expense' => $admin_coupon_discount_subsidy + $discount_on_trip + $amount_admin + $ref_bonus_amount + $pro_discount_amount,
                'pro_discount' => $pro_discount_amount,
                'tax' => $trip->tax_amount,
                'received_by' => $received_by ? $received_by : 'admin',
                'zone_id' => $trip->zone_id,
                'module_id' => $trip->module_id,
                'store_expense' =>  $store_coupon_discount_subsidy + $store_d_amount,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
                'discount_amount_by_store' => $store_coupon_discount_subsidy + $store_d_amount,
                'additional_charge' => $trip->additional_charge,
                'ref_bonus_amount' => $trip->ref_bonus_amount,
                'is_subscribed' => $subscription_mode,
                'commission_percentage' => $commission_percentage,
                'admin_net_income' => $comission_amount + $trip->additional_charge - ($admin_coupon_discount_subsidy + $ref_bonus_amount + $discount_on_trip + $amount_admin + $pro_discount_amount)
            ]);
            $adminWallet = AdminWallet::firstOrNew(
                ['admin_id' => Admin::where('role_id', 1)->first()->id]
            );

            $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $comission_amount + $trip->additional_charge - $admin_coupon_discount_subsidy - $discount_on_trip  - $ref_bonus_amount - $pro_discount_amount;

            $vendorWallet = StoreWallet::firstOrNew(
                ['vendor_id' => $provider->vendor->id]
            );
            $vendorWallet->total_earning = $vendorWallet->total_earning + $store_amount;



            $unpaid_payment = PartialPayment::where('payment_status', 'unpaid')->where('trip_id', $trip->id)->first()?->payment_method;
            $unpaid_pay_method = 'digital_payment';
            if ($unpaid_payment) {
                $unpaid_pay_method = $unpaid_payment;
            }

            if ($received_by == 'admin') {
                $adminWallet->digital_received = $adminWallet->digital_received + ($trip->trip_amount - $trip->partially_paid_amount);
            } else if ($received_by == 'vendor' &&  ($trip->payment_method == "cash_payment" || $unpaid_pay_method == 'cash_payment')) {
                $store_over_flow =  true;
                $vendorWallet->collected_cash = $vendorWallet->collected_cash + ($trip->trip_amount - $trip->partially_paid_amount);
            } else if ($received_by == false) {
                $adminWallet->manual_received = $adminWallet->manual_received + ($trip->trip_amount - $trip->partially_paid_amount);
            }


            $adminWallet->save();
            $vendorWallet->save();


            if (isset($store_over_flow)) {
                app(AccountTransactionService::class)->recordCashCollection(oldCollectedCash: $vendorWallet->collected_cash, fromType: 'store', fromId: $provider->vendor->id, amount: $trip->trip_amount - $trip->partially_paid_amount, reference: $trip->id);
            }


            app(OrderPaymentService::class)->markUnpaidTripPaymentPaid(tripId: $trip->id, paymentMethod: $trip->payment_method);

            DB::commit();

            if ($trip->is_guest  == 0) {
                $ref_status = Helpers::get_business_settings('ref_earning_status', false);
                if (isset($trip->customer->ref_by) && $trip->customer->order_count == 0  && $ref_status == 1) {
                    $ref_code_exchange_amt = Helpers::get_business_settings('ref_earning_exchange_rate', false);
                    $referar_user = User::where('id', $trip->customer->ref_by)->first();
                    $refer_wallet_transaction = app(WalletTransactionService::class)->recordWalletTransaction($referar_user->id, $ref_code_exchange_amt, 'referrer', $trip->customer->phone);

                    $notification_data = NotificationMessages::referralWalletCredit($ref_code_exchange_amt, $trip?->customer?->f_name . ' ' . $trip?->customer?->l_name, ['trip_id' => 1]);

                    if (SendNotification::channelEnabled('customer', 'customer_referral_bonus_earning', 'push_notification_status') && $referar_user?->cm_firebase_token) {
                        SendNotification::pushToCustomer($referar_user?->id, $referar_user?->cm_firebase_token, $notification_data);
                    }


                    try {
                        app(UserService::class)->notifyFundAdded($referar_user->id);
                        if (SendNotification::canSendMail('add_fund_mail_status_user', 'customer', 'customer_add_fund_to_wallet')) {
                            SendNotification::mail($referar_user?->getRawOriginal('email'), new \App\Mail\AddFundToWallet($refer_wallet_transaction));
                        }
                    } catch (\Exception $ex) {
                        Log::error('rental.trip_logic_trait.create_transaction_failed', [
                            'error' => $ex->getMessage(),
                            'file' => $ex->getFile().':'.$ex->getLine(),
                        ]);
                    }
                }

                $create_loyalty_point_transaction = app(LoyaltyPointTransactionService::class)->recordLoyaltyPointTransaction($trip->user_id, $trip->id, $trip->trip_amount, 'trip_booking');
                if ($create_loyalty_point_transaction > 0) {
                    $notification_data = NotificationMessages::loyaltyPointsEarned($create_loyalty_point_transaction, ['trip_id' => $trip->id]);

                    if (SendNotification::channelEnabled('customer', 'customer_loyalty_point_earning', 'push_notification_status') && $trip->customer?->cm_firebase_token) {
                        SendNotification::pushToCustomer($trip->user_id, $trip->customer?->cm_firebase_token, $notification_data, isGuest: (bool) $trip->is_guest);
                    }
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            info($e);
            return false;
        }
        return true;
    }

    public static function tripExpenseCreate($amount, $type, $datetime, $created_by, $trip_id = null, $store_id = null, $description = '', $delivery_man_id = null, $user_id = null)
    {
        $expense = new Expense();
        $expense->amount = $amount;
        $expense->type = $type;
        $expense->trip_id = $trip_id;
        $expense->created_by = $created_by;
        $expense->store_id = $store_id;
        $expense->delivery_man_id = $delivery_man_id;
        $expense->user_id = $user_id;
        $expense->description = $description;
        $expense->created_at = now();
        $expense->updated_at = now();
        return $expense->save();
    }


    public static function cashbackToWallet($trip)
    {

        $refer_wallet_transaction = app(WalletTransactionService::class)->recordWalletTransaction($trip?->cashback_history?->user_id, $trip?->cashback_history?->calculated_amount, 'CashBack', $trip->id);
        if ($refer_wallet_transaction != false) {
            self::tripExpenseCreate(amount: $trip?->cashback_history?->calculated_amount, type: 'CashBack', datetime: now(), created_by: 'admin', trip_id: $trip->id);
            $trip?->cashback_history?->cashBack?->increment('total_used');

            $notification_data = NotificationMessages::cashbackCreditedWithAmountPlaceholder($trip?->cashback_history?->calculated_amount, ['trip_id' => $trip->id]);

            if ($trip->customer?->cm_firebase_token && SendNotification::channelEnabled('customer', 'customer_cashback', 'push_notification_status')) {
                SendNotification::pushToCustomer($trip->customer?->id, $trip->customer?->cm_firebase_token, $notification_data);
            }
        }

        return true;
    }

    public static function getUpdatedTrip($request, Trips $trip, array $data, $isUpdated = true): Trips|array
    {
        $totalPrice = 0;
        $quantity = 0;
        $discountOnTrip = 0;
        $taxMap = [];
        $provider = $request->vendor ? $request?->vendor?->stores[0] : $trip?->provider;

        foreach ($trip->trip_details as $tripDetail) {
            if (!$tripDetail->vehicle) {
                return ['errors' => translate('No data found'), 'code' => 403];
            }
        }
        $details = $trip->trip_details;

        $additionalCharges = [];
        if (self::getAdditionalCharge() > 0) {
        }

        foreach ($details as $key => $tripDetail) {
            $tripDetailData = self::calculateTripDetailPricing(
                $tripDetail,
                $data['vehicleQuantities'],
                $data['modifiedPrices'],
                $data['estimatedHours'],
                $data['distance'],
                $trip->trip_type,
                $data['quantityUpdate'] ?? false,
                $data['updateDistance'] ?? false,
            );

            $totalPrice += $tripDetailData['calculatedPrice'];
            $discountOnTrip += $tripDetailData['discount'] * $tripDetailData['quantity'];
            $quantity += $tripDetailData['quantity'];


            $details[$key]['id'] = $tripDetail->id;
            $details[$key]['calculated_price'] = round($tripDetailData['calculatedPrice'], config('round_up_to_digit'));
            $details[$key]['quantity'] = round($tripDetailData['quantity'], config('round_up_to_digit'));
            $details[$key]['original_price'] =  round($tripDetailData['originalPrice'], config('round_up_to_digit'));

            if ($isUpdated) {
                $updatedTripDetails[] =   self::updateTripDetail($tripDetail, $tripDetailData, $data);
            }
        }

        $providerDiscount = self::applyProviderDiscount(
            $provider,
            $trip,
            $totalPrice,
            $isUpdated,
            $discountOnTrip,
            $taxMap
        );

        $finalPricing = self::calculateFinalPricing(
            $trip,
            $totalPrice,
            $providerDiscount['isAdminDiscount'] == true  ? $providerDiscount['discount'] : $discountOnTrip,
            0,
        );

        $totalDiscount = $finalPricing['totalDiscount'];

        $proManager = new class {
            use \App\Traits\Payment\ProCustomerSubscriptionTrait;
        };
        $proDiscount = 0.0;
        $proOffer = ['status' => false, 'benefit' => null];
        if (($trip->is_guest ?? 0) == 0 && $trip->user_id) {
            $proApply = $proManager->applyProCustomerDiscount(
                userId: (int) $trip->user_id,
                subtotal: (float) $totalPrice,
                totalPrice: (float) max(0, $totalPrice - $totalDiscount),
                moduleType: 'rental',
            );
            $proOffer = $proApply['offer'];
            $proDiscount = (float) $proApply['discount'];
        }
        $totalDiscountWithPro = $totalDiscount + $proDiscount;

        $finalCalculatedTax =   self::getFinalCalculatedTax($details, $additionalCharges, $totalDiscountWithPro, $totalPrice - $totalDiscountWithPro, $provider->id, $isUpdated);
        $taxAmount = $finalCalculatedTax['tax_amount'];
        $tax_included = $finalCalculatedTax['tax_included'];
        $tax_status = $finalCalculatedTax['tax_status'];
        $taxMap = $finalCalculatedTax['taxMap'];
        $orderTaxIds = data_get($finalCalculatedTax, 'taxData.orderTaxIds', []);

        $finalPricing = self::calculateFinalPricing(
                $trip,
                $totalPrice,
                $providerDiscount['isAdminDiscount'] == true  ? $providerDiscount['discount'] : $discountOnTrip,
                $taxAmount,
        );
        $finalPricing['tripAmount'] = max(0, $finalPricing['tripAmount'] - $proDiscount);
        $finalPricing['proDiscount'] = $proDiscount;

        if ($isUpdated) {
            foreach ($updatedTripDetails as $tripDetails) {
                if (!empty($taxMap[$tripDetails->vehicle_id])) {
                    $tax = $taxMap[$tripDetails->vehicle_id];
                    $tripDetails->tax_percentage = $tax['totalTaxPercent'];
                    $tripDetails->tax_status = $tax['include'] == 1 ? 'included' : 'excluded';
                    $tripDetails->tax_amount = $tax['totalTaxamount'];
                    $tripDetails->save();
                }
            }
            if (addon_published_status('TaxModule')) {
                $trip?->orderTaxes()?->delete();
            }
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $trip->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
        }

        $finalPricing['taxAmount'] = $taxAmount;


        if ($isUpdated) {
            self::updateCashback($trip, $finalPricing['tripAmount']);

            self::updateMainTrip(
                $trip,
                $data,
                $finalPricing,
                $quantity,
                $providerDiscount['isAdminDiscount'] ?? false,
                count($data['modifiedPrices']) > 0,
                $tax_status
            );

            \App\Models\OrderProDiscount::where('trip_id', $trip->id)->delete();
            if (($trip->is_guest ?? 0) == 0 && $trip->user_id && ($proOffer['status'] ?? false) && $proDiscount > 0) {
                $proManager->recordOrderProDiscount(
                    tripId: (int) $trip->id,
                    userId: (int) $trip->user_id,
                    proOffer: $proOffer,
                    amountSaved: (float) $proDiscount,
                    moduleType: 'rental',
                );
            }
            return $trip;
        } else {
            $finalPricing['taxStatus'] = $tax_status ?? 'excluded';
            $finalPricing['details'] = $details;
            return $finalPricing;
        }
    }

    public static function calculateTripDetailPricing($tripDetail, $vehicleQuantities, $modifiedPrices, $estimatedHours, $distance, $rentalType, $quantityUpdate = false, $updateDistance = false): array
    {
        $quantity = $vehicleQuantities[$tripDetail->vehicle_id] ?? $tripDetail->quantity;
        if ($rentalType === 'hourly') {
            $originalPrice = $tripDetail->vehicle->hourly_price * $estimatedHours;
        } elseif ($rentalType === 'day_wise') {
            $originalPrice = $tripDetail->vehicle->day_wise_price * ((int) round($estimatedHours / 24));
        } else {
            // §3.4 — measured distance is kilometres always (D2); `distance_price` is quoted in
            // whatever unit `distance_unit` names. Convert before multiplying, exactly as
            // DeliveryChargeService::chargeableBase() does.
            $originalPrice = $tripDetail->vehicle->distance_price
                * app(\App\Services\System\DistanceService::class)->chargeable($distance);
        }

        if ($updateDistance == 1) {
            $price = $originalPrice * $quantity;
        } else {
            $price = $modifiedPrices[$tripDetail->vehicle_id] ?? $originalPrice * $quantity;
            $price = ($tripDetail->vehicle_id ==  $quantityUpdate) ? $originalPrice * $quantity : $price;
            if (!in_array($rentalType, ['hourly', 'day_wise']) &&  $tripDetail->distance != $distance) {
                if ($modifiedPrices[$tripDetail->vehicle_id] ==  $originalPrice * $quantity) {
                    $price = $originalPrice * $quantity;
                } else {
                    $price = $price;
                }
            }
        }

        $discountData = self::getDiscount(
            price: $originalPrice,
            discount_type: $tripDetail->vehicle->discount_type,
            discount: $tripDetail->vehicle->discount_price
        );

        $calculatedPrice = $price  == $originalPrice ? $originalPrice * $quantity : $price;



        $discountData['discount'] =  ($originalPrice * $quantity) == $price ?  $discountData['discount'] : 0;

        return [
            'quantity' => $quantity,
            'price' => round($price, config('round_up_to_digit')),
            'originalPrice' => round($originalPrice, config('round_up_to_digit')),
            'calculatedPrice' => $calculatedPrice,
            'discount' => $discountData['discount'],
            'discountPercentage' => $tripDetail->vehicle->discount_type === 'amount' ? 0 : $tripDetail->vehicle->discount_price,
            'taxAmount' => 0
        ];
    }


    public static function calculateFinalPricing($trip, $totalPrice, $discount, $taxAmount=0): array
    {
        $price = $totalPrice - $discount;
        $couponDiscount = self::calculateCouponDiscount($trip, $price);
        $couponDiscount = Helpers::minDiscountCheck(productPrice: $price, discount: $couponDiscount)['discount_applied'];
        $price -= $couponDiscount;
        $refBonus = self::calculateReferralBonus($trip, $price);
        $refBonus = Helpers::minDiscountCheck(productPrice: $price, discount: $refBonus)['discount_applied'];
        $finalPrice = max(0, $price - $refBonus);
        $totalDiscount = $discount + $couponDiscount + $refBonus;
        return [
            'subTotal' => $totalPrice,
            'tripAmount' => max(0, $finalPrice + $taxAmount + self::getAdditionalCharge()),
            'discount' => $discount,
            'couponDiscount' => $couponDiscount,
            'refBonus' => $refBonus,
            'taxAmount' => $taxAmount,
            'additionalCharge' => self::getAdditionalCharge(),
            'totalDiscount' => $totalDiscount
        ];
    }

    public static function calculateCouponDiscount($trip, $price): float
    {
        if (!$trip->coupon_code || $trip->coupon_discount_amount <= 0) {
            return 0;
        }

        $coupon = Coupon::where(['code' => $trip->coupon_code])->first();
        return isset($coupon) ? app(CouponService::class)->calculateDiscount($coupon, $price) : $trip->coupon_discount_amount;
    }

    public static function calculateReferralBonus($trip, $price): float
    {
        if ($trip->ref_bonus_amount <= 0) {
            return 0;
        }

        $discountData = self::getCusromerFirstOrderDiscount($price);
        return data_get($discountData, 'calculated_amount') ?? $trip->ref_bonus_amount;
    }

    public static function getAdditionalCharge(): float
    {
        if (Helpers::get_business_settings('additional_charge_status', false) != 1) {
            return 0;
        }
        return Helpers::get_business_settings('additional_charge', false) ?? 0;
    }

    public static function getDiscount($price, $discount_type, $discount)
    {
        if ($price > 0 &&  $discount > 0) {
            $discount =  $discount_type == 'percent' ? ($price * $discount) / 100 :  $discount;
        }
        return ['price' => $price, 'discount' => $discount ?? 0];
    }

    public  static function checkAdminDiscount($price, $discount, $max_discount, $min_purchase, $vehicle_wise_price = null)
    {
        if ($price > 0 &&  $discount > 0) {
            $discount = ($price  * $discount) / 100;
            $discount = $discount > $max_discount ? $max_discount : $discount;
            $discount = $price >= $min_purchase ? $discount : 0;
        }

        if ($discount > 0 && $vehicle_wise_price > 0) {
            $discount = ($vehicle_wise_price / $price) * $discount;
        }

        return $discount ?? 0;
    }
    public  static function couponCheck($request, $increment = true)
    {

        $coupon = Coupon::active()->where(['code' => $request['coupon_code']])->first();
        if (isset($coupon)) {

            if ($request->is_guest) {
                $staus = app(CouponService::class)->validateForGuest($coupon, $request['provider_id']);
            } else {
                $staus = app(CouponService::class)->validateForCustomer($coupon, $request->user->id, $request['provider_id']);
            }

            $message = match ($staus) {
                407 => translate('messages.Coupon expire'),
                408 => translate('messages.You are not eligible for this coupon'),
                406 => translate('messages.Coupon usage limit over'),
                409 => translate('messages.Coupon not valid for this zone'),
                404 => translate('No data found'),
                default => null,
            };
            if ($message != null) {
                return ['code' => 'coupon', 'message' => $message, 'status_code' => $staus];
            }
            if ($coupon->coupon_type == 'free_delivery') {
                return ['code' => 'coupon', 'message' => translate('messages.Invalid coupon'), 'status_code' => 403];
            }

            if ($increment === true) {
                $coupon->increment('total_uses');
            }

            $coupon_discount_by = $coupon->created_by;

            return ['coupon' => $coupon, 'coupon_discount_by' => $coupon_discount_by];
        } else {
            return ['code' => 'coupon', 'message' => translate('No data found'), 'status_code' => 404];
        }
    }


    public static function getCusromerFirstOrderDiscount($price = null)
    {
        $settings =  Helpers::get_business_settings_many(['new_customer_discount_amount', 'new_customer_discount_amount_type']);
        $calculated_amount = 0;
        if (data_get($settings, 'new_customer_discount_amount') > 0) {
            if (data_get($settings, 'new_customer_discount_amount_type') == 'percentage' && isset($price)) {
                $calculated_amount = ($price / 100) * data_get($settings, 'new_customer_discount_amount');
            } else {
                $calculated_amount = data_get($settings, 'new_customer_discount_amount');
            }
        }
        return round($calculated_amount, config('round_up_to_digit'));
    }


    public static function applyProviderDiscount($store, Trips $trip, float $totalPrice, $isUpdated, $discountOnTrip, $taxMap): array
    {
        $providerDiscount = Helpers::get_store_discount($store);
        if (!$providerDiscount) {
            return [
                'discount' => 0,
                'isAdminDiscount' => false
            ];
        }

        $adminDiscount = self::checkAdminDiscount(
            price: $totalPrice,
            discount: $providerDiscount['discount'],
            max_discount: $providerDiscount['max_discount'],
            min_purchase: $providerDiscount['min_purchase']
        );

        if ($adminDiscount <= 0) {
            return [
                'discount' => 0,
                'isAdminDiscount' => false
            ];
        }


        $discount = max($discountOnTrip, $adminDiscount);
        if ($adminDiscount > 0 &&  $discount == $adminDiscount) {
            if ($isUpdated) {
                self::updateAdminDiscountAmount($trip, $totalPrice, $providerDiscount, $taxMap);
            }
            return [
                'discount' => $adminDiscount,
                'isAdminDiscount' => true
            ];
        }

        return [
            'discount' => 0,
            'isAdminDiscount' => false
        ];
    }


    public static function updateCashback($trip, $tripAmount): void
    {
        if (!$trip?->cashback_history) {
            return;
        }

        $cashBack = app(CashBackService::class)->calculateForCustomer(
            amount: $tripAmount,
            customerId: $trip->user_id,
            type: 'rental'
        );

        if (data_get($cashBack, 'calculated_amount') > 0) {
            $trip->cashback_history->fill([
                'calculated_amount' => data_get($cashBack, 'calculated_amount'),
                'cashback_amount' => data_get($cashBack, 'cashback_amount'),
                'cash_back_id' => data_get($cashBack, 'id'),
                'cashback_type' => data_get($cashBack, 'cashback_type'),
                'min_purchase' => data_get($cashBack, 'min_purchase'),
                'max_discount' => data_get($cashBack, 'max_discount'),
            ])->save();
        }
    }

    public static function updateMainTrip($trip, $data, $pricing, $quantity, $isAdminDiscount, $hasModifiedPrices, $taxStatus): void
    {
        $trip->fill([
            'edited' => 1,
            'trip_amount' => $pricing['tripAmount'],
            'discount_on_trip' => $pricing['discount'],
            'discount_on_trip_by' => $isAdminDiscount ? 'admin' :  'vendor',
            'coupon_discount_amount' => $pricing['couponDiscount'],

            'tax_amount' => $pricing['taxAmount'],
            'tax_status' => $taxStatus,
            'tax_percentage' => 0,

            'additional_charge' =>  self::getAdditionalCharge(),
            'distance' => $data['distance'],
            'estimated_hours' => $data['estimatedHours'],
            'scheduled' => $data['scheduled'],
            'schedule_at' => $data['scheduleAt'],
            'quantity' => $quantity,
            'estimated_trip_end_time' => $data['estimatedTripEndTime'],
            'destination_location' => $data['destinationLocation'],
            'pickup_location' => $data['pickupLocation'],
        ])->save();
    }

    public static function updateTripDetail($tripDetail, $pricingData, $tripData)
    {
        $tripDetail->fill([
            'quantity' => $pricingData['quantity'],
            'price' => $pricingData['originalPrice'] * $pricingData['quantity'],
            'original_price' => $pricingData['originalPrice'],
            'calculated_price' => $pricingData['calculatedPrice'],
            'discount_on_trip' => $pricingData['discount'],
            'discount_percentage' => $pricingData['discountPercentage'],
            'tax_amount' => $pricingData['taxAmount'],
            'tax_status' => 'excluded',
            'vehicle_details' => json_encode($tripDetail->vehicle),
            'estimated_hours' => $tripData['estimatedHours'],
            'distance' => $tripData['distance'],
            'scheduled' => $tripData['scheduled'],
            'schedule_at' => $tripData['scheduleAt'],
            'estimated_trip_end_time' => $tripData['estimatedTripEndTime'],
            'is_edited' => 1,
        ])->save();
        return $tripDetail;
    }

    public static function updateAdminDiscountAmount($trip, $totalPrice, $providerDiscount, $taxMap = [])
    {

        if ($providerDiscount['discount'] >  0) {
            foreach ($trip->trip_details as $tripDetail) {
                $itemDiscount = self::checkAdminDiscount(
                    price: $totalPrice,
                    discount: $providerDiscount['discount'],
                    max_discount: $providerDiscount['max_discount'],
                    min_purchase: $providerDiscount['min_purchase'],
                    vehicle_wise_price: $tripDetail->price
                );

                if (count($taxMap) > 0 && isset($taxMap[$tripDetail['vehicle_id']])) {
                    $tax_amount = $taxMap[$tripDetail['vehicle_id']]['totalTaxamount'];
                    $tax_status = $taxMap[$tripDetail['vehicle_id']]['include'] == 1 ? 'included' : 'excluded';
                    $tax_percentage = $taxMap[$tripDetail['vehicle_id']]['totalTaxPercent'];
                }

                $tripDetail->fill([
                    'discount_on_trip_by' => 'admin',
                    'discount_type' => 'percentage',
                    'discount_percentage' => $providerDiscount['discount'],
                    'discount_on_trip' => $itemDiscount,

                    'tax_amount' =>  $tax_amount ?? 0,
                    'tax_status' => $tax_status ?? 'excluded',
                    'tax_percentage' => $tax_percentage?? 0,
                ])->save();
            }
        }
        return true;
    }

    public static function getFinalCalculatedTax($details_data, $additionalCharges, $totalDiscount, $price, $provider_id, $storeData = true)
    {

        $products = [];
        $tempList = [];
        $productDiscountTotal = 0;
        $totalAfterOwnDiscounts = 0;

        if (addon_published_status('TaxModule')) {

            foreach ($details_data as $item) {
                $item_id = $item['vehicle_id'] ;
                $itemWiseDiscount = $item['discount_on_trip_by'] === 'admin'  ? $item['discount_on_trip'] : $item['discount_on_trip']  * $item['quantity'];
                $productDiscountTotal += $itemWiseDiscount;
                $itemFinal = (($item['original_price'] * $item['quantity']) ==  $item['price'] ? $item['price'] : $item['calculated_price']  -  $itemWiseDiscount);
                $is_edited= (($item['original_price'] * $item['quantity']) ==  $item['price'] ? false : true);
                $tempList[] = [
                    'id' => $item_id,
                    'original_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'category_id' => $item['category_id'],
                    'discount' => $item['discount_on_trip'],
                    'discount_on_trip_by' => $item['discount_on_trip_by'],
                    'base_final' => $itemFinal,
                    'is_edited' => $is_edited
                ];

                $totalAfterOwnDiscounts += $itemFinal;

            }

            $otherDiscounts = $totalDiscount - $productDiscountTotal ;

            foreach ($tempList as $entry) {
                $share = ($entry['base_final'] / $totalAfterOwnDiscounts) * $otherDiscounts;
                $finalPrice =  $entry['is_edited'] ? $entry['base_final'] : $entry['base_final'] - $share;
                    $products[] = [
                        'id' => $entry['id'],
                        'original_price' => $entry['original_price'],
                        'quantity' => $entry['quantity'],
                        'category_id' => $entry['category_id'],
                        'discount' => $entry['discount'],
                        'after_discount_final_price' => $finalPrice,
                    ];
            }


            $taxData =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                amount: $price,
                productIds: $products,
                storeData: $storeData,
                additionalCharges: $additionalCharges,
                taxPayer: 'rental_provider',
                orderId: null,
                storeId: $provider_id
            );;
            $tax_amount = $taxData['totalTaxamount'];
            $tax_included = $taxData['include'];
            $tax_status = $tax_included ?  'included' : 'excluded';


            foreach ($taxData['productWiseData'] ?? [] as $item) {
                $taxMap[$item['product_id']] = $item;
            }
        }

        return [
            'tax_amount' => $tax_amount ?? 0,
            'tax_included' => $tax_included ?? 0,
            'tax_status' => $tax_status ?? 'excluded',
            'taxMap' => $taxMap ?? [],
            'taxData' => $taxData ?? [],
        ];
    }
}
