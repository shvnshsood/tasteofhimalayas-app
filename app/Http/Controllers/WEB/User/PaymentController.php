<?php

namespace App\Http\Controllers\WEB\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductVariant;
use App\Models\OrderAddress;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StripePayment;
use App\Mail\OrderSuccessfully;
use App\Helpers\MailHelper;
use App\Models\EmailTemplate;
use App\Models\RazorpayPayment;
use App\Models\Flutterwave;
use App\Models\PaystackAndMollie;
use App\Models\InstamojoPayment;
use App\Models\Coupon;
use App\Models\ProductVariantItem;
use App\Models\DeliveryArea;
use App\Models\Shipping;
use App\Models\Address;
use App\Models\SslcommerzPayment;
use App\Models\PaypalPayment;
use App\Models\ShoppingCartVariant;
use App\Models\BankPayment;

use Mail;
Use Stripe;
use Cart;
use Session;
use Str;
use Razorpay\Api\Api;
use Exception;
use Redirect;
use Auth;

use App\Library\SslCommerz\SslCommerzNotification;
use Mollie\Laravel\Facades\Mollie;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web')->except('molliePaymentSuccess','instamojoResponse','sslcommerz_success','sslcommerz_failed');
    }

    public function checkout(){

        if(Cart::count() == 0){
            $notification = trans('user_validation.Your cart is empty!');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('products')->with($notification);
        }

        $user = Auth::guard('web')->user();
        $addresses = Address::with('deliveryArea')->where(['user_id' => $user->id])->get();
        $cart_contents = Cart::content();
        $delivery_areas = DeliveryArea::where('status', 1)->get();

        return view('checkout')->with(['addresses' => $addresses, 'cart_contents' => $cart_contents, 'delivery_areas' => $delivery_areas]);
    }


    public function payment(){

        if(Cart::count() == 0){
            $notification = trans('user_validation.Your cart is empty!');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('products')->with($notification);
        }

        $user = Auth::guard('web')->user();
        $cart_contents = Cart::content();

        $stripePaymentInfo = StripePayment::first();
        $razorpayPaymentInfo = RazorpayPayment::first();
        $flutterwavePaymentInfo = Flutterwave::first();
        $paypalPaymentInfo = PaypalPayment::first();
        $bankPaymentInfo = BankPayment::first();
        $paystackAndMollie = PaystackAndMollie::first();
        $instamojo = InstamojoPayment::first();
        $sslcommerz = SslcommerzPayment::first();

        $calculate_amount = $this->calculate_amount(12);

        return view('payment')->with([
            'user' => $user,
            'cart_contents' => $cart_contents,
            'calculate_amount' => $calculate_amount,
            'bankPaymentInfo' => $bankPaymentInfo,
            'stripePaymentInfo' => $stripePaymentInfo,
            'paypalPaymentInfo' => $paypalPaymentInfo,
            'razorpayPaymentInfo' => $razorpayPaymentInfo,
            'flutterwavePaymentInfo' => $flutterwavePaymentInfo,
            'paystackAndMollie' => $paystackAndMollie,
            'instamojo' => $instamojo,
            'sslcommerz' => $sslcommerz,
        ]);
    }

    public function set_delivery_charge(Request $request){
        Session::put('delivery_id', $request->delivery_id);
        Session::put('delivery_charge', $request->charge);
    }

    public function handcash_payment(){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $user = Auth::guard('web')->user();
        $cart_contents = Cart::content();

        $calculate_amount = $this->calculate_amount(7);
        $order_result = $this->orderStore($user, $calculate_amount,  'Cash on Delivery', 'cash_on_delivery', 0, 1, 7);

        $this->sendOrderSuccessMail($user, $order_result, 'Cash on Delivery', 0);

        $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('dashboard')->with($notification);
    }

    public function bank_payment(Request $request){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $rules = [
            'tnx_info'=>'required'
        ];
        $customMessages = [
            'tnx_info.required' => trans('user_validation.Transaction is required')
        ];
        $this->validate($request, $rules,$customMessages);

        $user = Auth::guard('web')->user();
        $cart_contents = Cart::content();

        $calculate_amount = $this->calculate_amount(7);
        $order_result = $this->orderStore($user, $calculate_amount,  'Bank Payment', $request->tnx_info, 0, 0, 7);

        $this->sendOrderSuccessMail($user, $order_result, 'Bank Payment', 0);

        $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('dashboard')->with($notification);

    }


    public function stripe_payment(Request $request){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $user = Auth::guard('web')->user();
        $cart_contents = Cart::content();

        $calculate_amount = $this->calculate_amount(7);

        $stripe = StripePayment::first();
        $payableAmount = round($calculate_amount['grand_total'] * $stripe->currency_rate,2);
        Stripe\Stripe::setApiKey($stripe->stripe_secret);

        $result = Stripe\Charge::create ([
                "amount" => $payableAmount * 100,
                "currency" => $stripe->currency_code,
                "source" => $request->stripeToken,
                "description" => env('APP_NAME')
        ]);

        $order_result = $this->orderStore($user, $calculate_amount,  'Stripe', $result->balance_transaction, 1, 0, 7);

        $this->sendOrderSuccessMail($user, $order_result, 'Stripe', 1);

        $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('dashboard')->with($notification);

    }

    public function razorpay_payment(Request $request){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $razorpay = RazorpayPayment::first();
        $input = $request->all();
        $api = new Api($razorpay->key,$razorpay->secret_key);
        $payment = $api->payment->fetch($input['razorpay_payment_id']);
        if(count($input)  && !empty($input['razorpay_payment_id'])) {
            try {
                $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(array('amount'=>$payment['amount']));
                $payId = $response->id;

                $user = Auth::guard('web')->user();
                $calculate_amount = $this->calculate_amount(7);

                $order_result = $this->orderStore($user, $calculate_amount,  'Razorpay', $payId, 1, 0, 7);

                $this->sendOrderSuccessMail($user, $order_result, 'Razorpay', 1);

                $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
                $notification = array('messege'=>$notification,'alert-type'=>'success');
                return redirect()->route('dashboard')->with($notification);

            }catch (Exception $e) {
                $notification = trans('user_validation.Payment Faild');
                $notification = array('messege'=>$notification,'alert-type'=>'error');
                return redirect()->route('payment')->with($notification);
            }

        }else{
            $notification = trans('user_validation.Payment Faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('payment')->with($notification);
        }
    }

    public function razorpay_flutterwave(Request $request){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $flutterwave = Flutterwave::first();
        $curl = curl_init();
        $tnx_id = $request->tnx_id;
        $url = "https://api.flutterwave.com/v3/transactions/$tnx_id/verify";
        $token = $flutterwave->secret_key;
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);


        if($response->status == 'success'){
            $user = Auth::guard('web')->user();
            $calculate_amount = $this->calculate_amount(7);

            $order_result = $this->orderStore($user, $calculate_amount,  'Flutterwave', $tnx_id, 1, 0, 7);

            $this->sendOrderSuccessMail($user, $order_result, 'Flutterwave', 1);

            $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
            return response()->json(['message' => $notification]);
        }else{
            $notification = trans('user_validation.Payment Faild');
            return response()->json(['message' => $notification],403);
        }
    }

    public function pay_with_mollie(){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $user = Auth::guard('web')->user();
        $cart_contents = Cart::content();

        $calculate_amount = $this->calculate_amount(7);


        $amount_real_currency = $calculate_amount['grand_total'];
        $mollie = PaystackAndMollie::first();
        $price = $amount_real_currency * $mollie->mollie_currency_rate;
        $price = sprintf('%0.2f', $price);

        $mollie_api_key = $mollie->mollie_key;
        $currency = strtoupper($mollie->mollie_currency_code);
        Mollie::api()->setApiKey($mollie_api_key);
        $payment = Mollie::api()->payments()->create([
            'amount' => [
                'currency' => $currency,
                'value' => ''.$price.'',
            ],
            'description' => env('APP_NAME'),
            'redirectUrl' => route('mollie-payment-success'),
        ]);

        $payment = Mollie::api()->payments()->get($payment->id);
        session()->put('payment_id',$payment->id);
        session()->put('calculate_amount',$calculate_amount);
        return redirect($payment->getCheckoutUrl(), 303);
    }

    public function mollie_payment_success(Request $request){
        $mollie = PaystackAndMollie::first();
        $mollie_api_key = $mollie->mollie_key;
        Mollie::api()->setApiKey($mollie_api_key);
        $payment = Mollie::api()->payments->get(session()->get('payment_id'));
        if ($payment->isPaid()){
            $user = Auth::guard('web')->user();
            $calculate_amount = Session::get('calculate_amount');
            $order_result = $this->orderStore($user, $calculate_amount,  'Mollie', session()->get('payment_id'), 1, 0, 7);

            $this->sendOrderSuccessMail($user, $order_result, 'Mollie', 1);

            $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
            $notification = array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->route('dashboard')->with($notification);
        }else{
            $notification = trans('user_validation.Payment faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('payment')->with($notification);
        }
    }

    public function pay_with_paystack(Request $request){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $paystack = PaystackAndMollie::first();

        $reference = $request->reference;
        $transaction = $request->tnx_id;
        $secret_key = $paystack->paystack_secret_key;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYHOST =>0,
            CURLOPT_SSL_VERIFYPEER =>0,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $secret_key",
                "Cache-Control: no-cache",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        $final_data = json_decode($response);
        if($final_data->status == true) {

            $user = Auth::guard('web')->user();
            $cart_contents = Cart::content();

            $calculate_amount = $this->calculate_amount(7);
            $order_result = $this->orderStore($user, $calculate_amount,  'Paystack', $transaction, 1, 0, 7);

            $this->sendOrderSuccessMail($user, $order_result, 'Paystack', 1);

            $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
            return response()->json(['message' => $notification]);

        }else{
            $notification = trans('user_validation.Payment Faild');
            return response()->json(['message' => $notification],403);
        }
    }

    public function pay_with_instamojo(){

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $user = Auth::guard('web')->user();

        $calculate_amount = $this->calculate_amount(7);
        Session::push('calculate_amount', $calculate_amount);

        $amount_real_currency = $calculate_amount['grand_total'];
        $instamojoPayment = InstamojoPayment::first();
        $price = $amount_real_currency * $instamojoPayment->currency_rate;
        $price = round($price,2);


        $environment = $instamojoPayment->account_mode;
        $api_key = $instamojoPayment->api_key;
        $auth_token = $instamojoPayment->auth_token;


        if($environment == 'Sandbox') {
            $url = 'https://test.instamojo.com/api/1.1/';
        } else {
            $url = 'https://www.instamojo.com/api/1.1/';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url.'payment-requests/');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("X-Api-Key:$api_key",
                "X-Auth-Token:$auth_token"));
        $payload = Array(
            'purpose' => env("APP_NAME"),
            'amount' => $price,
            'phone' => '918160651749',
            'buyer_name' => Auth::user()->name,
            'redirect_url' => route('instamojo-response'),
            'send_email' => true,
            'webhook' => 'http://www.example.com/webhook/',
            'send_sms' => true,
            'email' => Auth::user()->email,
            'allow_repeated_payments' => false
        );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        return redirect($response->payment_request->longurl);
    }

    public function instamojo_response(Request $request){
        $input = $request->all();

        $instamojoPayment = InstamojoPayment::first();
        $environment = $instamojoPayment->account_mode;
        $api_key = $instamojoPayment->api_key;
        $auth_token = $instamojoPayment->auth_token;

        if($environment == 'Sandbox') {
            $url = 'https://test.instamojo.com/api/1.1/';
        } else {
            $url = 'https://www.instamojo.com/api/1.1/';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url.'payments/'.$request->get('payment_id'));
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("X-Api-Key:$api_key",
                "X-Auth-Token:$auth_token"));
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $notification = trans('user_validation.Payment faild');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('payment')->with($notification);
        } else {
            $data = json_decode($response);
        }

        if($data->success == true) {
            if($data->payment->status == 'Credit') {
                $user = Auth::guard('web')->user();
                $calculate_amount = Session::get('calculate_amount');
                $order_result = $this->orderStore($user, $calculate_amount,  'Instamojo', $request->get('payment_id'), 1, 0, 7);

                $this->sendOrderSuccessMail($user, $order_result, 'Instamojo', 1);

                $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
                $notification = array('messege'=>$notification,'alert-type'=>'success');
                return redirect()->route('dashboard')->with($notification);
            }
        }
    }

    public function sslcommerz(Request $request)
    {

        if(env('APP_MODE') == 0){
            $notification = trans('user_validation.This Is Demo Version. You Can Not Change Anything');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

        $user = Auth::guard('web')->user();
        $calculate_amount = $this->calculate_amount(7);
        Session::put('calculate_amount', $calculate_amount);
        $total_price = $calculate_amount['grand_total'];

        $sslcommerzPaymentInfo = SslcommerzPayment::first();
        $payableAmount = round($total_price * $sslcommerzPaymentInfo->currency_rate,2);

        $post_data = array();
        $post_data['total_amount'] = $payableAmount; # You cant not pay less than 10
        $post_data['currency'] = $sslcommerzPaymentInfo->currency_code;
        $post_data['tran_id'] = uniqid();

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $user->name;
        $post_data['cus_email'] = $user->email ? $user->email : 'johndoe@gmail.com';
        $post_data['cus_add1'] = '';
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = "";
        $post_data['cus_postcode'] = "";
        $post_data['cus_country'] = "Country";
        $post_data['cus_phone'] =  $user->phone ? $user->phone : '123456789';
        $post_data['cus_fax'] = "";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = "";
        $post_data['ship_add1'] = "";
        $post_data['ship_add2'] = "";
        $post_data['ship_city'] = "";
        $post_data['ship_state'] = "";
        $post_data['ship_postcode'] = "";
        $post_data['ship_phone'] = "";
        $post_data['ship_country'] = "";

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = 'Test Product';
        $post_data['product_category'] = "Package";
        $post_data['product_profile'] = "Package";

        config(['sslcommerz.apiCredentials.store_id' => $sslcommerzPaymentInfo->store_id]);
        config(['sslcommerz.apiCredentials.store_password' => $sslcommerzPaymentInfo->store_password]);
        config(['sslcommerz.success_url' => '/sslcommerz-success']);
        config(['sslcommerz.failed_url' => '/sslcommerz-failed']);

        $sslc = new SslCommerzNotification(config('sslcommerz'));

        $payment_options = $sslc->makePayment($post_data, 'checkout', 'json');

        $data = json_decode($payment_options);
        return redirect()->to($data->data);


    }

    public function sslcommerz_success(Request $request)
    {

        $tran_id = $request->input('tran_id');
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $payment_id = $request->get('payment_id');

        $sslcommerzPaymentInfo = SslcommerzPayment::first();

        config(['sslcommerz.apiCredentials.store_id' => $sslcommerzPaymentInfo->store_id]);
        config(['sslcommerz.apiCredentials.store_password' => $sslcommerzPaymentInfo->store_password]);
        config(['sslcommerz.success_url' => '/user/checkout/sslcommerz-success']);
        config(['sslcommerz.failed_url' => '/user/checkout/sslcommerz-failed']);

        $sslc = new SslCommerzNotification(config('sslcommerz'));

        $validation = $sslc->orderValidate($request->all(), $tran_id, $amount, $currency);

        if ($validation == TRUE) {
            $transaction_id = $payment_id;

            $user = Auth::guard('web')->user();

            $calculate_amount = Session::get('calculate_amount');
            $order_result = $this->orderStore($user, $calculate_amount,  'SslCommerz', $transaction_id, 1, 0, 7);

            $this->sendOrderSuccessMail($user, $order_result, 'SslCommerz', 1);

            $notification = trans('user_validation.Order submited successfully. please wait for admin approval');
            $notification = array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->route('dashboard')->with($notification);

        } else {
            $notification = trans('user_validation.Payment faild');
            $notification = array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->route('payment')->with($notification);
        }

    }

    public function sslcommerz_failed(Request $request)
    {
        $notification = trans('user_validation.Payment faild');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('payment')->with($notification);
    }

    public function calculate_amount($address_id){

        $delivery_charge = Session::get('delivery_charge');
        $sub_total = 0;
        $coupon_price = 0.00;

        $cart_contents = Cart::content();
        foreach ($cart_contents as $index => $cart_content){
            $item_price = $cart_content->price * $cart_content->qty;
            $item_total = $item_price + $cart_content->options->optional_item_price;
            $sub_total += $item_total;
        }

        if(Session::get('coupon_price') && Session::get('offer_type')){
            if(Session::get('offer_type') == 1) {
                $coupon_price = Session::get('coupon_price');
                $coupon_price = ($coupon_price / 100) * $sub_total;
            }else {
                $coupon_price = Session::get('coupon_price');
            }
        }

        $grand_total = ($sub_total - $coupon_price) + $delivery_charge;

        return array(
            'sub_total' => $sub_total,
            'coupon_price' => $coupon_price,
            'delivery_charge' => $delivery_charge,
            'grand_total' => $grand_total,
        );
    }

    public function orderStore($user, $calculate_amount, $payment_method, $transaction_id, $payment_status, $cash_on_delivery, $address_id){

        $order = new Order();
        $order->order_id = substr(rand(0,time()),0,10);
        $order->user_id = $user->id;
        $order->grand_total = $calculate_amount['grand_total'];
        $order->delivery_charge = $calculate_amount['delivery_charge'];
        $order->coupon_price = $calculate_amount['coupon_price'];
        $order->sub_total = $calculate_amount['sub_total'];
        $order->product_qty = Cart::count();
        $order->payment_method = $payment_method;
        $order->transection_id = $transaction_id;
        $order->payment_status = $payment_status;
        $order->order_status = 0;
        $order->cash_on_delivery = $cash_on_delivery;
        $order->save();

        $cart_contents = Cart::content();
        foreach ($cart_contents as $index => $cart_content){
            $optional_item_arr = array();
            foreach($cart_content->options->optional_items as $index => $optional_item){
                $new_item = array(
                    'item' => $optional_item['optional_name'],
                    'price' => $optional_item['optional_price'],
                );
                $optional_item_arr[] = $new_item;
            }

            $orderProduct = new OrderProduct();
            $orderProduct->order_id = $order->id;
            $orderProduct->product_id = $cart_content->id;
            $orderProduct->product_name = $cart_content->name;
            $orderProduct->unit_price = $cart_content->price;
            $orderProduct->qty = $cart_content->qty;
            $orderProduct->product_size = $cart_content->options->size;
            $orderProduct->optional_price = $cart_content->options->optional_item_price;
            $orderProduct->optional_item = json_encode($optional_item_arr);
            $orderProduct->save();
        }

        // store address
        $address_id = Session::get('delivery_id');
        $find_address = Address::find($address_id);
        $find_delivery_address = DeliveryArea::find($find_address->delivery_area_id);
        $orderAddress = new OrderAddress();
        $orderAddress->order_id = $order->id;
        $orderAddress->name = $find_address->first_name.' '.$find_address->last_name;
        $orderAddress->email = $find_address->email;
        $orderAddress->phone = $find_address->phone;
        $orderAddress->address = $find_address->address;
        $orderAddress->longitude = $find_address->longitude;
        $orderAddress->latitude = $find_address->latitude;
        $orderAddress->delivery_time = $find_delivery_address->min_time.' - '. $find_delivery_address->max_time;
        $orderAddress->save();

        Session::forget('delivery_id');
        Session::forget('delivery_charge');
        Session::forget('coupon_price');
        Session::forget('offer_type');
        Session::forget('coupon_price');
        Session::forget('offer_type');
        Cart::destroy();

        return $order;
    }


    public function sendOrderSuccessMail($user, $order_result, $payment_method, $payment_status){

        $setting = Setting::first();

        MailHelper::setMailConfig();

        $template=EmailTemplate::where('id',6)->first();

        $payment_status = $payment_status == 1 ? 'Success' : 'Pending';
        $subject=$template->subject;
        $message=$template->description;
        $message = str_replace('{{user_name}}',$user->name,$message);
        $message = str_replace('{{total_amount}}',$setting->currency_icon.$order_result->grand_total,$message);
        $message = str_replace('{{payment_method}}',$payment_method,$message);
        $message = str_replace('{{payment_status}}',$payment_status,$message);
        $message = str_replace('{{order_status}}','Pending',$message);
        $message = str_replace('{{order_date}}',$order_result->created_at->format('d F, Y'),$message);
        Mail::to($user->email)->send(new OrderSuccessfully($message,$subject));
    }
}


