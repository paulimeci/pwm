<div>
    @section('page-title', __('Lista e Çmimeve të Lavazhos'))

    @section('breadcrumb')
        <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center mb-0 lh-1">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                        <i class="ri-home-4-line fs-18 text-primary me-1"></i>
                        <span class="text-secondary fw-medium">{{ __('Dashboard') }}</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span class="fw-medium">{{ __('Çmimet e Lavazhos') }}</span></li>
            </ol>
        </nav>
    @endsection

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ═══════════════════════════════════════
          KOKA
         ════════════════════════════════════════ --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-1 fw-semibold">{{ __('Shërbimet e Larjes') }}</h5>
            <p class="text-secondary fs-13 mb-0">{{ __('Menaxhoni shërbimet dhe çmimet sipas kategorisë së mjetit dhe monedhës.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" wire:click="hapKategorite" class="btn btn-outline-primary rounded-3">
                <i class="ri-car-line me-1"></i> {{ __('Kategoritë e Mjeteve') }}
            </button>
            <button type="button" wire:click="hapModalin" class="btn btn-primary rounded-3">
                <i class="ri-add-line me-1"></i> {{ __('Shto Shërbim') }}
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
          TABELA
         ════════════════════════════════════════ --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h5 class="mb-0 fw-semibold">{{ __('Shërbime & Çmime') }}</h5>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    {{-- Filtri sipas kategorisë --}}
                    <select wire:model.live="filterKatId" class="form-select rounded-2" style="min-width: 190px;">
                        <option value="">{{ __('Të gjitha kategoritë') }}</option>
                        @foreach($kategorite as $kategoria)
                            <option value="{{ $kategoria->id }}">{{ $kategoria->kategoria }}</option>
                        @endforeach
                    </select>

                    {{-- Kërkimi --}}
                    <div class="position-relative table-src-form me-0">
                        <input wire:model.live.debounce.300ms="search" type="text" class="form-control ps-5" placeholder="{{ __('Kërko shërbim...') }}">
                        <i class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3">search</i>
                    </div>
                </div>
            </div>

            <div class="default-table-area style-two default-table-width">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th scope="col">{{ __('ID') }}</th>
                            <th scope="col">{{ __('Shërbimi') }}</th>
                            <th scope="col">{{ __('Kategoria e Mjetit') }}</th>
                            <th scope="col">{{ __('Çmimi Kryesor (ALL)') }}</th>
                            <th scope="col">{{ __('Çmime të tjera') }}</th>
                            <th scope="col" class="text-end pe-4">{{ __('Veprime') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rreshtat as $r)
                            <tr wire:key="konfig-{{ $r['sherbimi_id'] }}-{{ $r['kategoria_id'] }}">
                                <td class="text-secondary fs-13">#{{ $r['sherbimi_id'] }}</td>
                                <td>
                                    <span class="fw-semibold fs-14">{{ $r['sherbimi'] }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1 fs-11 fw-semibold rounded-2">
                                        {{ $r['kategoria'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 fs-12 fw-bold rounded-2">
                                        {{ number_format($r['cmimet']['ALL'] ?? 0, 0, ',', '.') }} ALL
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if(isset($r['cmimet']['EUR']))
                                            <span class="badge border border-primary border-opacity-25 bg-primary bg-opacity-10 rounded-pill px-2 py-1 fs-11 fw-semibold text-primary">
                                                EUR: €{{ number_format($r['cmimet']['EUR'], 2) }}
                                            </span>
                                        @endif

                                        @if(isset($r['cmimet']['USD']))
                                            <span class="badge border border-warning border-opacity-25 bg-warning bg-opacity-10 rounded-pill px-2 py-1 fs-11 fw-semibold text-warning">
                                                USD: ${{ number_format($r['cmimet']['USD'], 2) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-2 pe-3">
                                        <button type="button" wire:click="shikoSherbimin({{ $r['sherbimi_id'] }}, {{ $r['kategoria_id'] }})" class="p-0 border-0 bg-transparent lh-1" title="{{ __('Shiko') }}">
                                            <i class="material-symbols-outlined fs-18 text-primary">visibility</i>
                                        </button>
                                        <button type="button" wire:click="editSherbimin({{ $r['sherbimi_id'] }}, {{ $r['kategoria_id'] }})" class="p-0 border-0 bg-transparent lh-1" title="{{ __('Edito') }}">
                                            <i class="material-symbols-outlined fs-18 text-body">edit</i>
                                        </button>
                                        <button type="button" wire:click="fshiSherbimin({{ $r['sherbimi_id'] }}, {{ $r['kategoria_id'] }})"
                                                wire:confirm="{{ __('A jeni i sigurt që dëshironi ta fshini këtë konfigurim?') }}"
                                                class="p-0 border-0 bg-transparent lh-1" title="{{ __('Fshij') }}">
                                            <i class="material-symbols-outlined fs-18 text-danger">delete</i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5 fs-14">
                                    <i class="material-symbols-outlined fs-48 d-block mb-2 text-secondary">local_car_wash</i>
                                    {{ __('Nuk ka asnjë konfigurim të regjistruar.') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
          MODAL — SHTO / EDITO / SHIKO SHËRBIM
         ════════════════════════════════════════ --}}
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.45); backdrop-filter: blur(2px);">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 rounded-3 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-semibold fs-16">
                            @if($isViewOnly) {{ __('Shiko Konfigurimin') }} @elseif($editingId) {{ __('Edito Konfigurimin') }} @else {{ __('Shto Shërbim të Ri') }} @endif
                        </h5>
                        <button type="button" wire:click="$set('showModal', false)" class="btn-close"></button>
                    </div>
                    <div class="modal-body pt-3">

                        {{-- Emri i shërbimit --}}
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small text-uppercase">{{ __('Shërbimi') }} <span class="text-danger">*</span></label>
                            <input wire:model="sherbimi" type="text" {{ $isViewOnly ? 'disabled' : '' }}
                            class="form-control rounded-2 @error('sherbimi') is-invalid @enderror"
                                   placeholder="{{ __('p.sh. Larje e jashtme') }}">
                            @error('sherbimi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($editingId)
                                <span class="text-secondary fs-11 d-block mt-1">{{ __('Ndryshimi i emrit vlen për të gjitha kategoritë e këtij shërbimi.') }}</span>
                            @endif
                        </div>

                        {{-- Kategoria e mjetit --}}
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small text-uppercase">{{ __('Kategoria e Mjetit') }} <span class="text-danger">*</span></label>
                            <select wire:model="id_kategoria_mjetit"
                                    {{ ($isViewOnly || $editingId) ? 'disabled' : '' }}
                                    class="form-select rounded-2 @error('id_kategoria_mjetit') is-invalid @enderror">
                                <option value="">-- {{ __('Zgjidh Kategorinë') }} --</option>
                                @foreach($kategorite as $kategoria)
                                    <option value="{{ $kategoria->id }}">{{ $kategoria->kategoria }}</option>
                                @endforeach
                            </select>
                            @error('id_kategoria_mjetit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Çmimet sipas monedhave --}}
                        <div class="bg-light p-3 rounded-3 border">
                            <h6 class="fw-bold mb-3 text-secondary small text-uppercase tracking-wider">{{ __('Çmimet sipas Monedhave') }}</h6>

                            @foreach($monedhat as $monedha)
                                <div class="mb-2" wire:key="monedha-{{ $monedha->id }}">
                                    <label class="form-label fw-medium fs-13 text-muted mb-1">{{ $monedha->emri }} ({{ $monedha->kodi }}) <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <input wire:model="cmimet_monedhave.{{ $monedha->id }}" type="number" step="0.01" min="0"
                                               {{ $isViewOnly ? 'disabled' : '' }}
                                               class="form-control text-end pe-2 font-mono @error('cmimet_monedhave.' . $monedha->id) is-invalid @enderror"
                                               placeholder="0.00">
                                        <span class="input-group-text bg-white fw-bold text-secondary small" style="min-width: 50px; justify-content: center;">{{ $monedha->kodi }}</span>
                                    </div>
                                    @error('cmimet_monedhave.' . $monedha->id)
                                    <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                    </div>
                    <div class="modal-footer border-0 pt-2">
                        <button type="button" wire:click="$set('showModal', false)"
                                class="btn btn-outline-secondary btn-sm rounded-2 px-3">{{ __('Anulo') }}</button>
                        @if(!$isViewOnly)
                            <button type="button" wire:click="ruajSherbimin" class="btn btn-primary btn-sm rounded-2 px-3">
                                <span wire:loading wire:target="ruajSherbimin" class="spinner-border spinner-border-sm me-1"></span>
                                {{ $editingId ? __('Ruaj Ndryshimet') : __('Ruaj') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════
          MODAL — KATEGORITË E MJETEVE
         ════════════════════════════════════════ --}}
    @if($showKategoriModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.45); backdrop-filter: blur(2px);">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 rounded-3 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-semibold fs-16">{{ __('Kategoritë e Mjeteve') }}</h5>
                        <button type="button" wire:click="$set('showKategoriModal', false)" class="btn-close"></button>
                    </div>
                    <div class="modal-body pt-3">

                        {{-- Forma shto / edito --}}
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small text-uppercase">
                                {{ $editingKatId ? __('Edito Kategorinë') : __('Kategori e Re') }} <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input wire:model="kat_emri" wire:keydown.enter="ruajKategorine" type="text"
                                       class="form-control rounded-start-2 @error('kat_emri') is-invalid @enderror"
                                       placeholder="{{ __('p.sh. Sedan, SUV, Kamion, Biçikletë') }}">
                                <button type="button" wire:click="ruajKategorine" class="btn btn-primary">
                                    <span wire:loading wire:target="ruajKategorine" class="spinner-border spinner-border-sm me-1"></span>
                                    {{ $editingKatId ? __('Ruaj') : __('Shto') }}
                                </button>
                                @if($editingKatId)
                                    <button type="button" wire:click="anuloEditimin" class="btn btn-outline-secondary">{{ __('Anulo') }}</button>
                                @endif
                                @error('kat_emri') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        @error('kat_fshirja')
                        <div class="alert alert-danger py-2 fs-13 rounded-2">{{ $message }}</div>
                        @enderror

                        {{-- Lista --}}
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th class="text-secondary fs-12 text-uppercase">{{ __('ID') }}</th>
                                    <th class="text-secondary fs-12 text-uppercase">{{ __('Kategoria') }}</th>
                                    <th class="text-end text-secondary fs-12 text-uppercase">{{ __('Veprime') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($kategorite as $kategoria)
                                    <tr wire:key="kat-lista-{{ $kategoria->id }}" class="{{ $editingKatId == $kategoria->id ? 'table-active' : '' }}">
                                        <td class="text-secondary fs-13">#{{ $kategoria->id }}</td>
                                        <td class="fw-medium fs-14">{{ $kategoria->kategoria }}</td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <button type="button" wire:click="editKategorine({{ $kategoria->id }})" class="p-0 border-0 bg-transparent lh-1" title="{{ __('Edito') }}">
                                                    <i class="material-symbols-outlined fs-18 text-body">edit</i>
                                                </button>
                                                <button type="button" wire:click="fshiKategorine({{ $kategoria->id }})"
                                                        wire:confirm="{{ __('A jeni i sigurt që dëshironi ta fshini këtë kategori?') }}"
                                                        class="p-0 border-0 bg-transparent lh-1" title="{{ __('Fshij') }}">
                                                    <i class="material-symbols-outlined fs-18 text-danger">delete</i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4 fs-13">
                                            {{ __('Nuk ka asnjë kategori të regjistruar.') }}
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                    </div>
                    <div class="modal-footer border-0 pt-2">
                        <button type="button" wire:click="$set('showKategoriModal', false)"
                                class="btn btn-outline-secondary btn-sm rounded-2 px-3">{{ __('Mbyll') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
