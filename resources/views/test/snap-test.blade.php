{{-- resources/views/test/snap-test.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Test Midtrans Snap</title>
</head>
<body>
    <h3>Test Bayar via Midtrans</h3>
    <button id="pay-button">Bayar Sekarang</button>

    <!-- Midtrans Snap JS -->
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>

    <script>
        document.getElementById('pay-button').addEventListener('click', function () {
            snap.pay('{{ $snapToken }}', {
                onSuccess: function(result){
                    console.log('SUCCESS:', result);
                    alert('Pembayaran berhasil');
                },
                onPending: function(result){
                    console.log('PENDING:', result);
                    alert('Pembayaran pending');
                },
                onError: function(result){
                    console.log('ERROR:', result);
                    alert('Pembayaran gagal');
                },
                onClose: function(){
                    alert('Popup ditutup tanpa pembayaran');
                }
            });
        });
    </script>
</body>
</html>
