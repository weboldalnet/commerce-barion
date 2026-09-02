@extends('admin.layouts.layout')
@section('title', 'Barion beállítások')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="header-box my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">Barion beállítások</h1>
                        <p class="text-muted small mb-0">Barion bankkártyás fizetés konfigurálása</p>
                    </div>
                    <div>
                        <button type="button" id="barion-test-connection-btn" class="btn btn-warning font-weight-bold">
                            <i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése
                        </button>
                        <div id="barion-test-connection-result" class="mt-2 mb-0 d-none"></div>
                    </div>
                </div>
            </div>

            @include('admin.webshop.partials.alerts')

            @php
                $barionEnabled = filter_var($barionSettings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $barionIsProd = ($barionSettings['environment'] ?? 'test') === 'prod';
                $barionHasPosKey = !empty($barionSettings['pos_key']);
                $barionSelectedSources = (array) ($barionSettings['funding_sources'] ?? ['All']);

                // A route() itt az admin domaint adná, a Barion viszont a site
                // domainre hív vissza – ezért a tényleges címet mutatjuk.
                // A portot a jelenlegi kérésből vesszük át (fejlesztői szerver),
                // ha az nem az alapértelmezett és a domain még nem tartalmazza.
                $barionScheme = request()->getScheme();
                $barionSiteHost = getSiteDomain();
                $barionPort = request()->getPort();
                $barionDefaultPort = $barionScheme === 'https' ? 443 : 80;
                $barionSiteBase = $barionScheme . '://' . $barionSiteHost
                    . (($barionPort && (int) $barionPort !== $barionDefaultPort && strpos($barionSiteHost, ':') === false)
                        ? ':' . $barionPort
                        : '');
            @endphp

            <form action="{{ route('admin.webshop.barion.settings.update') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Modul állapota</div>
                        <div class="content-box bordered mb-3">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="enabled" name="enabled"
                                       @if($barionEnabled) checked @endif>
                                <label class="custom-control-label fw-600" for="enabled">Barion fizetés engedélyezve</label>
                            </div>

                            <div class="form-group mb-2">
                                <label class="fw-600">Megnevezés a pénztárban</label>
                                <input type="text" name="payment_method_label" class="form-control"
                                       value="{{ $barionSettings['payment_method_label'] ?? '' }}"
                                       placeholder="Barion bankkártyás fizetés">
                                <span class="text-muted fs-14">Ez a szöveg jelenik meg a vásárlónak fizetési módként.</span>
                            </div>

                            @if($barionEnabled && !$barionHasPosKey)
                                <div class="alert alert-warning mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    A modul be van kapcsolva, de nincs megadva POSKey – a fizetés indítása hibára fut.
                                </div>
                            @else
                                <div class="alert alert-info mb-0 py-2 px-3 small">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    A bekapcsoláshoz ki kell tölteni a POSKey-t és a kedvezményezett e-mail címét.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Környezet</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group mb-3">
                                <label class="fw-600">API környezet</label>
                                <select name="environment" id="environment" class="form-control">
                                    <option value="test" @if(!$barionIsProd) selected @endif>Teszt (sandbox)</option>
                                    <option value="prod" @if($barionIsProd) selected @endif>Éles</option>
                                </select>
                                <span class="text-muted fs-14">A teszt és az éles fiókhoz külön POSKey tartozik.</span>
                            </div>

                            @if($barionIsProd)
                                <div class="alert alert-danger mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    <strong>Éles környezet.</strong> A vásárlók valódi pénzzel fizetnek.
                                </div>
                            @else
                                <div class="alert alert-secondary mb-0 py-2 px-3 small">
                                    <i class="fa fa-flask mr-1"></i>
                                    Teszt környezet: a fizetések a Barion sandboxban zajlanak, valódi terhelés nélkül.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Hitelesítés (Barion)</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="fw-600">POSKey</label>
                                <input type="password" name="pos_key" class="form-control"
                                       value="{{ $barionSettings['pos_key'] ?? '' }}" autocomplete="new-password">
                                <span class="text-muted fs-14">
                                    A Barion boltodhoz tartozó 36 karakteres azonosító.
                                    Titkosítva tárolódik. Üresen hagyva a korábbi marad.
                                </span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="fw-600">Kedvezményezett (Payee)</label>
                                <input type="text" name="payee" class="form-control"
                                       value="{{ $barionSettings['payee'] ?? '' }}" placeholder="pl. bolt@example.com">
                                <span class="text-muted fs-14">A Barionnál regisztrált e-mail cím, amelyre a kifizetések érkeznek.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Fizetési beállítások</div>
                        <div class="content-box bordered mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Fizetés típusa</label>
                                        <select name="payment_type" class="form-control">
                                            @foreach(['Immediate' => 'Azonnali', 'Reservation' => 'Foglalás', 'DelayedCapture' => 'Késleltetett terhelés'] as $v => $l)
                                                <option value="{{ $v }}" @if(($barionSettings['payment_type'] ?? 'Immediate') === $v) selected @endif>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Fizetési ablak</label>
                                        <input type="text" name="payment_window" class="form-control"
                                               value="{{ $barionSettings['payment_window'] ?? '' }}" placeholder="0.00:30:00">
                                        <span class="text-muted fs-14">Formátum: nap.óra:perc:mp – pl. 0.00:30:00 = 30 perc.</span>
                                    </div>
                                </div>
                            </div>

                            <label class="fw-600 mb-1">Fizetési lehetőségek</label>
                            <div class="row">
                                @foreach([
                                    'All' => 'Mind (a Barion dönt)',
                                    'Bankcard' => 'Bankkártya',
                                    'Balance' => 'Barion egyenleg',
                                    'BankTransfer' => 'Banki átutalás',
                                    'GooglePay' => 'Google Pay',
                                    'ApplePay' => 'Apple Pay',
                                ] as $v => $l)
                                    <div class="col-6">
                                        <div class="custom-control custom-checkbox mb-1">
                                            <input type="checkbox" class="custom-control-input"
                                                   id="funding_{{ $v }}" name="funding_sources[]" value="{{ $v }}"
                                                   @if(in_array($v, $barionSelectedSources, true)) checked @endif>
                                            <label class="custom-control-label" for="funding_{{ $v }}">{{ $l }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <span class="text-muted fs-14 d-block mb-3">
                                Ha egyet sem jelölsz be, a „Mind” érvényes. A ténylegesen elérhető
                                lehetőségeket a Barion szerződésed határozza meg.
                            </span>

                            <div class="custom-control custom-checkbox mb-0">
                                <input type="checkbox" class="custom-control-input" id="payer_hint_enabled" name="payer_hint_enabled"
                                       @if(filter_var($barionSettings['payer_hint_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="payer_hint_enabled">
                                    Vásárló e-mail címének átadása (előre kitöltött belépés)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Megjelenés és naplózás</div>
                        <div class="content-box bordered mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Fizetőoldal nyelve</label>
                                        <select name="locale" class="form-control">
                                            @foreach([
                                                'hu-HU' => 'Magyar', 'en-US' => 'Angol', 'de-DE' => 'Német',
                                                'sk-SK' => 'Szlovák', 'cs-CZ' => 'Cseh', 'ro-RO' => 'Román',
                                                'hr-HR' => 'Horvát', 'sl-SI' => 'Szlovén', 'fr-FR' => 'Francia',
                                                'es-ES' => 'Spanyol', 'el-GR' => 'Görög', 'pl-PL' => 'Lengyel',
                                                'it-IT' => 'Olasz', 'bg-BG' => 'Bolgár',
                                            ] as $v => $l)
                                                <option value="{{ $v }}" @if(($barionSettings['locale'] ?? 'hu-HU') === $v) selected @endif>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                        <span class="text-muted fs-14">Csak akkor érvényes, ha a rendelés nyelve ismeretlen.</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="fw-600">Pénznem</label>
                                        <select name="currency" class="form-control">
                                            @foreach(['HUF' => 'HUF – forint', 'EUR' => 'EUR – euró', 'USD' => 'USD – dollár', 'CZK' => 'CZK – korona', 'RON' => 'RON – lej', 'PLN' => 'PLN – złoty'] as $v => $l)
                                                <option value="{{ $v }}" @if(($barionSettings['currency'] ?? 'HUF') === $v) selected @endif>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                        <span class="text-muted fs-14">Tartalék, ha a rendelés pénzneme érvénytelen.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="custom-control custom-checkbox mb-0">
                                <input type="checkbox" class="custom-control-input" id="log_payloads" name="log_payloads"
                                       @if(filter_var($barionSettings['log_payloads'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="log_payloads">
                                    API hívások naplózása
                                </label>
                            </div>
                            <span class="text-muted fs-14">
                                A kérések és válaszok a rendszernaplóba kerülnek – hibakereséshez hasznos.
                                A POSKey soha nem kerül naplózásra.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Visszatérési címek</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="fw-600">Callback URL</label>
                                <input type="text" name="callback_url" class="form-control"
                                       value="{{ $barionSettings['callback_url'] ?? '' }}"
                                       placeholder="{{ $barionSiteBase }}/commerce/barion/callback">
                                <span class="text-muted fs-14">Ide küldi a Barion a fizetés állapotát. Üresen a fenti automatikus cím érvényes.</span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="fw-600">Visszatérési (redirect) URL</label>
                                <input type="text" name="redirect_url" class="form-control"
                                       value="{{ $barionSettings['redirect_url'] ?? '' }}"
                                       placeholder="{{ $barionSiteBase }}/commerce/barion/return">
                                <span class="text-muted fs-14">Ide tér vissza a vásárló fizetés után. Üresen a fenti automatikus cím érvényes.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 mb-5">
                    <button type="submit" class="btn btn-primary fs-18 font-weight-bold px-5">
                        <i class="fa fa-save mr-1"></i> Beállítások mentése
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('barion-test-connection-btn');
        var out = document.getElementById('barion-test-connection-result');

        // Beágyazott visszajelzés natív alert() helyett: az blokkolja a lapot.
        function show(isSuccess, message) {
            out.className = 'mt-2 mb-0 alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
            out.textContent = message;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Tesztelés...';
            out.className = 'mt-2 mb-0 d-none';
            out.textContent = '';

            fetch('{{ route("admin.webshop.barion.test-connection") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                show(!!data.success, (data.success ? '' : 'Hiba: ') + (data.message || ''));
            })
            .catch(function () {
                show(false, 'Váratlan hiba történt a tesztelés során.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése';
            });
        });

        // A mentetlen környezetváltás félreértést okozna: a teszt mindig a
        // mentett beállítással fut, ezért erre figyelmeztetünk.
        var envSelect = document.getElementById('environment');
        if (envSelect) {
            var savedEnv = envSelect.value;
            envSelect.addEventListener('change', function () {
                if (envSelect.value !== savedEnv) {
                    show(false, 'A környezetváltás csak mentés után lép érvénybe – a kapcsolat tesztelése addig a korábbi beállítással fut.');
                }
            });
        }
    });
</script>
@endsection
