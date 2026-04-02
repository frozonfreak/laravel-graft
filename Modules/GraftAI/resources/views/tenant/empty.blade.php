@extends('graftai::layouts.app')

@section('content')
<div style="max-width:600px;margin:80px auto;text-align:center;font-family:sans-serif;">
    <h2 style="color:#374151;">No tenants yet</h2>
    <p style="color:#6b7280;margin-bottom:24px;">
        Run the demo seeder to populate sample tenants and features.
    </p>
    <pre style="background:#f3f4f6;padding:16px;border-radius:8px;text-align:left;font-size:13px;color:#374151;">php artisan db:seed --class="GraftAI\Database\Seeders\GraftAIDatabaseSeeder"</pre>
</div>
@endsection
