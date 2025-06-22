@extends('frontend.layouts.master')
@extends('frontend.layouts.master')

@section('title','Checkout page')

@section('main-content')

@section('content')
<div class="container text-center py-5">
    <h3>Proses Pembayaran Midtrans</h3>
    <p>Silakan klik tombol untuk melanjutkan pembayaran</p>

    <button id="pay-button" class="btn btn-primary">Bayar Sekarang</button>
</div>

<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>

<script>
    document.getElementById('pay-button').addEventListener('click', function () {
        snap.pay('{{ $snapToken }}', {
            onSuccess: function(result){
                console.log('success', result);
                window.location.href = "/";
            },
            onPending: function(result){
                console.log('pending', result);
                window.location.href = "/";
            },
            onError: function(result){
                alert('Terjadi kesalahan saat proses pembayaran.');
                window.location.href = "/checkout";
            }
        });
    });
</script>

<script>
    console.log("Client Key:", "{{ env('MIDTRANS_CLIENT_KEY') }}");
</script>
@endsection
