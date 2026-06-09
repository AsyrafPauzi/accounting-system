@props(['url'])
{{--
    BukuCloud-branded mail header. The published Laravel default rendered
    its own SVG when the slot was literally "Laravel"; we always render
    the BukuCloud logo instead. The logo URL is built off APP_URL so
    production emails reference https://app.bukucloud.com/images/...
    while local dev mail still resolves to whatever APP_URL is set to.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ config('mail.logo_url') ?: asset('images/bukucloud-logo.png') }}"
     class="logo"
     alt="{{ config('app.name', 'BukuCloud') }}"
     style="height: auto; max-height: 56px; width: auto; max-width: 180px; margin: 8px 0;">
</a>
</td>
</tr>
