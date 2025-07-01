<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Shipping;
use App\User;
use PDF;
use Notification;
use Helper;
use Illuminate\Support\Str;
use App\Notifications\StatusNotification;
use Illuminate\Support\Facades\Http;


//midtrans payment
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders=Order::orderBy('id','DESC')->paginate(10);
        return view('backend.order.index')->with('orders',$orders);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'first_name'=>'string|required',
            'last_name'=>'string|required',
            'address1'=>'string|required',
            'address2'=>'string|nullable',
            'coupon'=>'nullable|numeric',
            'phone'=>'numeric|required',
            'post_code'=>'string|nullable',
            'email'=>'string|required',
            'photo' => 'required_if:payment_method,bank_transfer|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
        ]);
        // return $request->all();

        if(empty(Cart::where('user_id',auth()->user()->id)->where('order_id',null)->first())){
            request()->session()->flash('error','Cart is Empty !');
            return back();
        }
        // $cart=Cart::get();
        // // return $cart;
        // $cart_index='ORD-'.strtoupper(uniqid());
        // $sub_total=0;
        // foreach($cart as $cart_item){
        //     $sub_total+=$cart_item['amount'];
        //     $data=array(
        //         'cart_id'=>$cart_index,
        //         'user_id'=>$request->user()->id,
        //         'product_id'=>$cart_item['id'],
        //         'quantity'=>$cart_item['quantity'],
        //         'amount'=>$cart_item['amount'],
        //         'status'=>'new',
        //         'price'=>$cart_item['price'],
        //     );

        //     $cart=new Cart();
        //     $cart->fill($data);
        //     $cart->save();
        // }

        // $total_prod=0;
        // if(session('cart')){
        //         foreach(session('cart') as $cart_items){
        //             $total_prod+=$cart_items['quantity'];
        //         }
        // }

        $order=new Order();
        $order_data=$request->all();
        $order_data['order_number']='ORD-'.strtoupper(Str::random(10));
        $order_data['user_id']=$request->user()->id;
        $order_data['shipping_id']=$request->shipping;
        $shipping=Shipping::where('id',$order_data['shipping_id'])->pluck('price');
        // return session('coupon')['value'];
        $order_data['sub_total']=Helper::totalCartPrice();
        $order_data['quantity']=Helper::cartCount();
        if(session('coupon')){
            $order_data['coupon']=session('coupon')['value'];
        }
        if($request->shipping){
            if(session('coupon')){
                $order_data['total_amount']=Helper::totalCartPrice()+$shipping[0]-session('coupon')['value'];
            }
            else{
                $order_data['total_amount']=Helper::totalCartPrice()+$shipping[0];
            }
        }
        else{
            if(session('coupon')){
                $order_data['total_amount']=Helper::totalCartPrice()-session('coupon')['value'];
            }
            else{
                $order_data['total_amount']=Helper::totalCartPrice();
            }
        }
        // return $order_data['total_amount'];
        $order_data['status']="new";

        if(request('payment_method')=='paypal'){
            $order_data['payment_method']='paypal';
            $order_data['payment_status']='paid';
        }
        elseif($request->payment_method == 'bank_transfer'){
            $order_data['payment_method'] = 'bank_transfer';
            $order_data['payment_status'] = 'pending'; // status khusus transfer manual

            // PROSES FILE UPLOAD disini
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('payments', $filename, 'public');
                $order_data['photo'] = $filename;
            }
        } 
        else{
            $order_data['payment_method']='cod';
            $order_data['payment_status']='Unpaid';
        }

        $order->fill($order_data);
        $status=$order->save();
        if($order)
        // dd($order->id);
        $users=User::where('role','admin')->first();
        $details=[
            'title'=>'New order created',
            'actionURL'=>route('order.show',$order->id),
            'fas'=>'fa-file-alt'
        ];
        Notification::send($users, new StatusNotification($details));

        //notifikasi WhatsApp ke admin
        $adminPhone = env('ADMIN_PHONE');
        if ($adminPhone) {
            $adminMessage = "*[Order Baru Masuk]*\n\n"
                . "No. Order   : {$order->order_number}\n"
                . "Nama        : {$order->first_name} {$order->last_name}\n"
                . "Total Bayar : Rp " . number_format($order->total_amount, 0, ',', '.') . "\n"
                . "Pembayaran  : " . strtoupper($order->payment_method) . "\n"
                . "Status      : " . strtoupper($order->status);

            $this->sendWhatsAppNotification($adminPhone, $adminMessage);
        }

        if(request('payment_method')=='paypal'){
            return redirect()->route('payment')->with(['id'=>$order->id]);
        }
        else{
            session()->forget('cart');
            session()->forget('coupon');
        }
        Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => $order->id]);

        // dd($users);        
        request()->session()->flash('success','Your product successfully placed in order');
        return redirect()->route('home');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $order=Order::find($id);
        // return $order;
        return view('backend.order.show')->with('order',$order);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $order=Order::find($id);
        return view('backend.order.edit')->with('order',$order);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        $this->validate($request, [
            'status' => 'required|in:new,process,delivered,cancel'
        ]);

        $data = $request->all();

        // Jika status delivered, kurangi stok produk
        if ($request->status == 'delivered') {
            foreach ($order->cart as $cart) {
                $product = $cart->product;
                $product->stock -= $cart->quantity;
                $product->save();
            }
        }

        // Jika metode pembayaran bank_transfer dan status tertentu, ubah payment_status jadi paid
        if ($order->payment_method == 'bank_transfer' && in_array($request->status, ['process', 'delivered'])) {
            $data['payment_status'] = 'paid';
        }

        $status = $order->fill($data)->save();

        if ($status) {
            // Ambil langsung nomor HP dari field order
            $phone = $order->phone;

            if ($phone) {
                // Format nomor telepon ke 62xxx
                $rawPhone = $order->phone;
                $phone = preg_replace('/[^0-9+]/', '', $rawPhone);

                if (substr($phone, 0, 1) === '+') {
                    $phone = substr($phone, 1); // remove "+"
                } elseif (substr($phone, 0, 1) === '0') {
                    $phone = '62' . substr($phone, 1); // local to intl
                }

                // Pesan-pesan berdasarkan status
                $customMessages = [
                    'new' => "Your order has been received and is now being processed. Thank you for shopping with us!",
                    'process' => "Your order is currently being processed. We will ship it out shortly.",
                    'delivered' => "Your order has been successfully delivered. Please check your items and contact us if you encounter any issues.",
                    'cancel' => "Your order has been cancelled. Please contact our support if this was not intended.",
                ];

                $customMessage = $customMessages[$request->status] ?? '';

                // Format pesan WhatsApp
                $waMessage = "*Tracking your order*\n\n"
                        . "No. Order : {$order->order_number}\n"
                        . "Name      : {$order->first_name} {$order->last_name}\n"
                  
                        . "Status    : *" . strtoupper($order->status) . "*\n\n"
                        . "Pesan:\n"
                        . $customMessage;

                // Kirim notifikasi
                $this->sendWhatsAppNotification($phone, $waMessage);
            }

            request()->session()->flash('success', 'Successfully updated order');
        } else {
            request()->session()->flash('error', 'Error while updating order');
        }

        return redirect()->route('order.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $order=Order::find($id);
        if($order){
            $status=$order->delete();
            if($status){
                request()->session()->flash('success','Order Successfully deleted');
            }
            else{
                request()->session()->flash('error','Order can not deleted');
            }
            return redirect()->route('order.index');
        }
        else{
            request()->session()->flash('error','Order can not found');
            return redirect()->back();
        }
    }

    public function orderTrack(){
        return view('frontend.pages.order-track');
    }

    public function productTrackOrder(Request $request){
        // return $request->all();
        $order=Order::where('user_id',auth()->user()->id)->where('order_number',$request->order_number)->first();
        if($order){
            if($order->status=="new"){
            request()->session()->flash('success','Your order has been placed. please wait.');
            return redirect()->route('home');

            }
            elseif($order->status=="process"){
                request()->session()->flash('success','Your order is under processing please wait.');
                return redirect()->route('home');
    
            }
            elseif($order->status=="delivered"){
                request()->session()->flash('success','Your order is successfully delivered.');
                return redirect()->route('home');
    
            }
            else{
                request()->session()->flash('error','Your order canceled. please try again');
                return redirect()->route('home');
    
            }
        }
        else{
            request()->session()->flash('error','Invalid order numer please try again');
            return back();
        }
    }

    // PDF generate
    public function pdf(Request $request){
        $order=Order::getAllOrder($request->id);
        // return $order;
        $file_name=$order->order_number.'-'.$order->first_name.'.pdf';
        // return $file_name;
        $pdf=PDF::loadview('backend.order.pdf',compact('order'));
        return $pdf->download($file_name);
    }
    // Income chart
    public function incomeChart(Request $request){
        $year=\Carbon\Carbon::now()->year;
        // dd($year);
        $items=Order::with(['cart_info'])->whereYear('created_at',$year)->where('status','delivered')->get()
            ->groupBy(function($d){
                return \Carbon\Carbon::parse($d->created_at)->format('m');
            });
            // dd($items);
        $result=[];
        foreach($items as $month=>$item_collections){
            foreach($item_collections as $item){
                $amount=$item->cart_info->sum('amount');
                // dd($amount);
                $m=intval($month);
                // return $m;
                isset($result[$m]) ? $result[$m] += $amount :$result[$m]=$amount;
            }
        }
        $data=[];
        for($i=1; $i <=12; $i++){
            $monthName=date('F', mktime(0,0,0,$i,1));
            $data[$monthName] = (!empty($result[$i]))? number_format((float)($result[$i]), 2, '.', '') : 0.0;
        }
        return $data;
    }

    public function testMidtrans()
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $params = [
            'transaction_details' => [
                'order_id' => 'TEST-' . time(),
                'gross_amount' => 10000,
            ],
            'customer_details' => [
                'first_name' => 'Tester',
                'email' => 'tester@example.com',
                'phone' => '081234567890',
            ],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);
        return view('frontend.pages.test-midtrans', compact('snapToken'));
    }
    public function getMidtransToken(Request $request)
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $params = [
            'transaction_details' => [
                'order_id' => 'ORD-' . strtoupper(Str::random(10)),
                'gross_amount' => 10000, // dummy
            ],
            'customer_details' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'phone' => '08123456789',
            ],
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
            return response()->json(['token' => $snapToken]);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    protected function sendWhatsAppNotification($phone, $message)
    {
        $url = env('WA_API_URL');
        $username = env('WA_API_AUTH_USER');
        $password = env('WA_API_AUTH_PASS');

        try {
            if (!str_ends_with($phone, '@s.whatsapp.net')) {
                $phone .= '@s.whatsapp.net';
            }

            $response = Http::withBasicAuth($username, $password)->post($url, [
                'phone' => $phone,
                'message' => $message,
            ]);

            \Log::info('WA response:', ['body' => $response->body()]);

            if ($response->failed()) {
                \Log::error('Gagal mengirim WhatsApp', ['response' => $response->body()]);
            }
        } catch (\Exception $e) {
            \Log::error('Exception WhatsApp API', ['message' => $e->getMessage()]);
        }
    }
}
