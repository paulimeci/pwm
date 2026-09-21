<div>
    @section('page-title', __('Lavazho — Kryej Operacionet'))

    {{-- ═══════════════════════════════════════
          SEKSIONI 1: FORMA E REGJISTRIMIT
         ════════════════════════════════════════ --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h4 class="fs-18 mb-0">{{ __('Regjistro Mjetin për Larje') }}</h4>
                @if (session()->has('success'))
                    <span class="badge bg-success bg-opacity-10 text-success p-2 px-3 rounded-2 fs-13">
                        <i class="ri-checkbox-circle-line align-middle me-1"></i> {{ session('success') }}
                    </span>
                @endif
            </div>

            <form wire:submit.prevent="ruajOperacionin">
                <div class="row g-2 align-items-end">

                    <div class="col-12 col-md-3">
                        <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Targa') }} <span class="text-danger">*</span></label>
                        <input type="text" wire:model="targa"
                               class="form-control h-45 rounded-3 fs-13 @error('targa') is-invalid @enderror"
                               placeholder="{{ __('AB123CD') }}">
                        @error('targa') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Kategoria e Mjetit') }} <span class="text-danger">*</span></label>
                        <select wire:model.live="reg_kategoria" class="form-select h-45 rounded-3 fs-13 @error('reg_kategoria') is-invalid @enderror">
                            <option value="">-- {{ __('Zgjidh') }} --</option>
                            @foreach($kategorite as $kategoria)
                                <option value="{{ $kategoria->id }}">{{ $kategoria->kategoria }}</option>
                            @endforeach
                        </select>
                        @error('reg_kategoria') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Shërbimi') }} <span class="text-danger">*</span></label>
                        <select wire:model.live="reg_sherbimi" class="form-select h-45 rounded-3 fs-13 @error('reg_sherbimi') is-invalid @enderror"
                            {{ $reg_kategoria ? '' : 'disabled' }}>
                            <option value="">-- {{ __('Zgjidh') }} --</option>
                            @foreach($sherbimetReg as $sherbimi)
                                <option value="{{ $sherbimi->id }}">{{ $sherbimi->sherbimi }}</option>
                            @endforeach
                        </select>
                        @error('reg_sherbimi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-md-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center border rounded-3 bg-light px-2" style="height: 45px;">
                                <div class="form-check form-switch p-0 m-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input m-0" type="checkbox" role="switch" id="paguarSwitch"
                                           wire:model="eshte_paguar" style="width: 2.2em; height: 1.1em; cursor: pointer;">
                                    <label class="text-secondary fw-medium fs-11 mb-0 text-nowrap" for="paguarSwitch" style="cursor: pointer;">
                                        {{ __('Paguar?') }}
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary h-45 flex-grow-1 rounded-3 shadow-sm d-flex align-items-center justify-content-center">
                                <span wire:loading wire:target="ruajOperacionin" class="spinner-border spinner-border-sm me-1"></span>
                                <i class="ri-save-line fs-14" wire:loading.remove wire:target="ruajOperacionin"></i>
                                <span class="ms-1 fs-12">{{ __('Ruaj') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                @if($cmimiPreview !== null)
                    <div class="mt-2 fs-12 text-secondary">
                        {{ __('Çmimi') }}: <b class="text-success">{{ number_format($cmimiPreview, 2) }} ALL</b>
                    </div>
                @elseif($reg_kategoria && $reg_sherbimi)
                    <div class="mt-2 fs-12 text-danger">{{ __('Ky shërbim nuk ka çmim ALL për këtë kategori.') }}</div>
                @endif
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
          SEKSIONI 2: KËRKIMI DHE MJETET PREZENT
         ════════════════════════════════════════ --}}
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3 mt-2">
        <h5 class="fs-16 fw-semibold mb-0">{{ __('Mjetet Prezent në Lavazho') }}</h5>

        <div class="position-relative" style="min-width: 260px;">
            <input type="text" wire:model.live="kerkoTarge"
                   class="form-control bg-white border border-secondary border-opacity-25 rounded-3 py-2 ps-4 pe-5 fs-13"
                   placeholder="{{ __('Kërko targë...') }}">
            <i class="ri-search-line position-absolute top-50 end-0 translate-middle-y me-3 text-secondary fs-16"></i>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
        <div class="btn-group p-1 bg-light rounded-3 flex-wrap" role="group">
            @foreach(['all' => 'Të Gjitha', 'paguar' => 'Të Paguara', 'pa_paguar' => 'Pa Paguar'] as $çelesi => $etiketa)
                <button type="button" wire:click="$set('tabiMjetetPrezent', '{{ $çelesi }}')"
                        class="btn btn-sm rounded-2 px-3 fs-13 fw-medium {{ $tabiMjetetPrezent === $çelesi ? 'btn-primary text-white shadow-sm' : 'btn-light border-0 text-secondary' }}">
                    {{ __($etiketa) }} <span class="badge bg-white text-dark ms-1">{{ $numrat[$çelesi] }}</span>
                </button>
            @endforeach
        </div>

        @if($kerkoTarge !== '')
            <span class="fs-11 text-secondary fst-italic">
                <i class="ri-information-line align-middle"></i>
                {{ __('Duke kërkuar në të gjitha mjetet, pavarësisht tab-it.') }}
            </span>
        @endif
    </div>

    <div class="row gx-2 gy-2">
        @forelse($mjetePrezent as $mjeti)
            @php
                $paguar = $mjeti->pagesa === 'po';
                $klasaKartes = $paguar ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10';
            @endphp
            <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6" wire:key="mjeti-{{ $mjeti->id }}">
                <div class="card {{ $klasaKartes }} border-0 rounded-3 mb-2 shadow-sm">
                    <div class="card-body p-2">

                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 fs-10 fw-medium">
                                    <i class="ri-checkbox-blank-circle-fill fs-7 align-middle me-1"></i>{{ __('Prezent') }}
                                </span>
                                <span class="text-secondary fw-medium" style="font-size: 11px;">
                                    {{ $mjeti->nisja->format('H:i') }}
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn p-0 border-0 bg-transparent text-secondary d-flex align-items-center"
                                        title="{{ __('Edito') }}" wire:click="perditesoMjetin({{ $mjeti->id }})">
                                    <i class="material-symbols-outlined fs-16">edit</i>
                                </button>
                                @role('admin')
                                <button type="button" class="btn p-0 border-0 bg-transparent text-danger d-flex align-items-center"
                                        title="{{ __('Fshi') }}" wire:click="konfirmoFshirjen({{ $mjeti->id }})">
                                    <i class="material-symbols-outlined fs-16">delete</i>
                                </button>
                                @endrole
                            </div>
                        </div>

                        <div class="text-center py-2 my-1">
                            <div class="d-inline-flex align-items-center justify-content-center border border-2 rounded-2 w-100 shadow-sm"
                                 style="height: 40px; cursor: pointer; background-color: #ffffff !important; border-color: #212529 !important;"
                                 wire:click="hapModalPagesen({{ $mjeti->id }})">
                                <span class="fs-18 fw-bolder font-monospace text-uppercase" style="letter-spacing: 0.8px; color: #000000 !important;">
                                    {{ $mjeti->targa }}
                                </span>
                            </div>
                        </div>

                        <div class="text-center mb-1">
                            <div class="fs-12 fw-semibold text-dark text-truncate">{{ $mjeti->sherbimi->sherbimi ?? '—' }}</div>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fs-10 rounded-2 px-2 py-1">{{ $mjeti->kategoria->kategoria ?? '—' }}</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center border-top border-dark border-opacity-10 pt-1">
                            @if($paguar)
                                <span class="badge bg-success bg-opacity-10 text-success rounded-2 px-2 py-1 fs-10 fw-bold">{{ __('Paguar') }}</span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-2 px-2 py-1 fs-10 fw-bold">{{ __('Pa Paguar') }}</span>
                            @endif
                            <span class="fs-11 fw-bold text-dark">{{ number_format($mjeti->vlera, 2) }} {{ $mjeti->monedha->kodi ?? '' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <p class="text-secondary text-center py-4 bg-white rounded-3 border">{{ __('Nuk u gjet asnjë mjet prezent.') }}</p>
            </div>
        @endforelse

        @if($kerkoTarge === '' && $faqetPrezent > 1)
            <div class="d-flex justify-content-center align-items-center gap-2 mt-3">
                <button type="button" wire:click="faqjaMbrapaPrezent" class="btn btn-sm btn-light border rounded-3 px-3 fs-12"
                        @if($faqjaPrezent <= 1) disabled @endif>
                    <i class="ri-arrow-left-s-line align-middle"></i> {{ __('Mbrapa') }}
                </button>
                <span class="fs-12 text-secondary fw-medium">{{ __('Faqja') }} {{ $faqjaPrezent }} / {{ $faqetPrezent }}</span>
                <button type="button" wire:click="faqjaTjeterPrezent" class="btn btn-sm btn-light border rounded-3 px-3 fs-12"
                        @if($faqjaPrezent >= $faqetPrezent) disabled @endif>
                    {{ __('Përpara') }} <i class="ri-arrow-right-s-line align-middle"></i>
                </button>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════
          MODAL: PAGESA / LARGIMI
         ════════════════════════════════════════ --}}
    @if($showModalPagesa && $mjetiModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 bg-white shadow rounded-3">

                    <div class="modal-header border-bottom p-4">
                        <h5 class="modal-title fs-16 fw-semibold">
                            <i class="ri-money-dollar-circle-line align-middle me-1 text-primary fs-20"></i>
                            {{ $modal_eshte_paguar ? __('Largo Mjetin (E Paguar)') : __('Paguaj & Largo Mjetin') }}
                        </h5>
                        <button type="button" class="btn-close shadow-none" wire:click="mbyllModalPagesen"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-light border border-dark border-2 rounded-2 px-4 py-1" style="min-width: 160px; height: 45px;">
                                <span class="fs-20 fw-black text-dark font-monospace text-uppercase" style="letter-spacing: 0.8px;">{{ $mjetiModal->targa }}</span>
                            </div>
                        </div>

                        <table class="table table-sm table-borderless fs-13 mb-3">
                            <tbody>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Koha e Hyrjes') }}:</td>
                                <td class="text-dark py-2 fw-semibold text-end">{{ $mjetiModal->nisja->format('d/m/Y - H:i') }}</td>
                            </tr>
                            <tr class="border-bottom border-light bg-light bg-opacity-50">
                                <td class="py-2 fw-bold text-danger">{{ __('Koha e Qëndrimit') }}:</td>
                                <td class="text-danger py-2 fw-bold text-end fs-14"><i class="ri-time-line me-1"></i> {{ $koha_qendrimit }}</td>
                            </tr>
                            <tr>
                                <td class="text-secondary py-2 fw-medium">{{ __('Statusi') }}:</td>
                                <td class="py-2 text-end">
                                    @if($modal_eshte_paguar)
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-2 px-2 py-1 fs-12 fw-bold">{{ __('Paguar') }}</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-2 px-2 py-1 fs-12 fw-bold">{{ __('Pa Paguar') }}</span>
                                    @endif
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Kategoria e Mjetit') }}</label>
                                <select wire:model.live="modal_kategoria" class="form-select fs-13 py-2 rounded-3">
                                    @foreach($kategorite as $kategoria)
                                        <option value="{{ $kategoria->id }}">{{ $kategoria->kategoria }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Monedha') }}</label>
                                <select wire:model.live="modal_monedha" class="form-select fs-13 py-2 rounded-3">
                                    @foreach($monedhat as $monedha)
                                        <option value="{{ $monedha->id }}">{{ $monedha->kodi }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Shërbimi') }}</label>
                                <select wire:model.live="modal_sherbimi" class="form-select fs-13 py-2 rounded-3 @error('modal_sherbimi') is-invalid @enderror">
                                    @forelse($sherbimetModal as $sherbimi)
                                        <option value="{{ $sherbimi->id }}">{{ $sherbimi->sherbimi }}</option>
                                    @empty
                                        <option value="">{{ __('Nuk ka shërbime me çmim për këtë kategori') }}</option>
                                    @endforelse
                                </select>
                                @error('modal_sherbimi') <div class="invalid-feedback d-block fs-12">{{ __('Zgjidhni shërbimin.') }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Vlera për t\'u Paguar') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" wire:model="modal_vlera"
                                           class="form-control fs-14 fw-semibold py-2 rounded-start-3 @error('modal_vlera') is-invalid @enderror" placeholder="0.00">
                                    <span class="input-group-text bg-light text-secondary fw-bold fs-12 rounded-end-3">
                                        {{ collect($monedhat)->firstWhere('id', $modal_monedha)->kodi ?? '' }}
                                    </span>
                                </div>
                                @error('modal_vlera') <div class="invalid-feedback d-block mt-1 fs-12">{{ $message }}</div> @enderror
                                @if($modal_sherbimi && $modal_vlera === '')
                                    <div class="text-danger fs-11 mt-1">{{ __('S\'ka çmim të konfiguruar për këtë kombinim. Vendose vlerën me dorë.') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2 bg-light bg-opacity-50">
                        <button type="button" class="btn btn-secondary py-2 px-3 fs-13 fw-semibold rounded-3 text-dark border-0 bg-gray bg-opacity-10" wire:click="mbyllModalPagesen">
                            {{ __('Anulo') }}
                        </button>
                        <button type="button" class="btn btn-success py-2 px-3 fs-13 fw-semibold rounded-3 text-white" wire:click="perfundoOperacionin">
                            <span wire:loading wire:target="perfundoOperacionin" class="spinner-border spinner-border-sm me-1"></span>
                            <i class="ri-check-double-line me-1"></i>
                            {{ $modal_eshte_paguar ? __('Largo Mjetin') : __('Përfundo & Largo') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════
          MODAL: EDITIMI
         ════════════════════════════════════════ --}}
    @if($showModalEdit)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1065;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 bg-white shadow rounded-3">

                    <div class="modal-header border-bottom p-4">
                        <h5 class="modal-title fs-16 fw-semibold">
                            <i class="ri-edit-2-line align-middle me-1 text-primary fs-20"></i>
                            {{ __('Edito Mjetin') }}
                        </h5>
                        <button type="button" class="btn-close shadow-none" wire:click="mbyllModalEditimi"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Targa') }}</label>
                                <input type="text" wire:model="edit_targa" class="form-control fs-13 py-2 rounded-3 text-uppercase @error('edit_targa') is-invalid @enderror">
                                @error('edit_targa') <div class="invalid-feedback d-block fs-12">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Data/Ora e Hyrjes') }}</label>
                                <input type="datetime-local" wire:model="edit_nisja" class="form-control fs-13 py-2 rounded-3 @error('edit_nisja') is-invalid @enderror">
                                @error('edit_nisja') <div class="invalid-feedback d-block fs-12">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Kategoria e Mjetit') }}</label>
                                <select wire:model.live="edit_kategoria" class="form-select fs-13 py-2 rounded-3 @error('edit_kategoria') is-invalid @enderror">
                                    @foreach($kategorite as $kategoria)
                                        <option value="{{ $kategoria->id }}">{{ $kategoria->kategoria }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Monedha') }}</label>
                                <select wire:model.live="edit_monedha" class="form-select fs-13 py-2 rounded-3">
                                    @foreach($monedhat as $monedha)
                                        <option value="{{ $monedha->id }}">{{ $monedha->kodi }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Shërbimi') }}</label>
                                <select wire:model.live="edit_sherbimi" class="form-select fs-13 py-2 rounded-3 @error('edit_sherbimi') is-invalid @enderror">
                                    @forelse($sherbimetEdit as $sherbimi)
                                        <option value="{{ $sherbimi->id }}">{{ $sherbimi->sherbimi }}</option>
                                    @empty
                                        <option value="">{{ __('Nuk ka shërbime me çmim për këtë kategori') }}</option>
                                    @endforelse
                                </select>
                                @error('edit_sherbimi') <div class="invalid-feedback d-block fs-12">{{ __('Zgjidhni shërbimin.') }}</div> @enderror
                            </div>

                            <div class="col-8">
                                <label class="label text-secondary fw-medium mb-1 fs-12">{{ __('Vlera') }}</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" wire:model="edit_vlera"
                                           class="form-control fs-14 fw-semibold py-2 @error('edit_vlera') is-invalid @enderror" placeholder="0.00">
                                    <span class="input-group-text bg-light text-secondary fw-bold fs-12">
                                        {{ collect($monedhat)->firstWhere('id', $edit_monedha)->kodi ?? '' }}
                                    </span>
                                </div>
                                @error('edit_vlera') <div class="invalid-feedback d-block fs-12">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-4 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="editPaguar" wire:model="edit_paguar">
                                    <label class="form-check-label fs-12 fw-medium text-secondary" for="editPaguar">{{ __('Paguar') }}</label>
                                </div>
                            </div>
                        </div>
                        <p class="text-secondary fs-11 mt-2 mb-0">{{ __('Ndryshimi i kategorisë, shërbimit ose monedhës rillogarit çmimin automatikisht. Mund ta ndryshosh pastaj me dorë.') }}</p>
                    </div>

                    <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2 bg-light bg-opacity-50">
                        <button type="button" class="btn btn-secondary py-2 px-3 fs-13 fw-semibold rounded-3 text-dark border-0 bg-gray bg-opacity-10" wire:click="mbyllModalEditimi">
                            {{ __('Anulo') }}
                        </button>
                        <button type="button" class="btn btn-success py-2 px-3 fs-13 fw-semibold rounded-3 text-white" wire:click="ruajEditimin">
                            <span wire:loading wire:target="ruajEditimin" class="spinner-border spinner-border-sm me-1"></span>
                            <i class="ri-save-line me-1"></i> {{ __('Ruaj Ndryshimet') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════
          MODAL: KONFIRMO FSHIRJEN
         ════════════════════════════════════════ --}}
    @if($mjetiFshirje)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6); z-index: 1070;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 bg-white shadow rounded-3" style="border: 2px solid #dc3545 !important;">
                    <div class="modal-header border-bottom p-4" style="background: rgba(220,53,69,0.06);">
                        <h5 class="modal-title fs-16 fw-bold text-danger mb-0">
                            <i class="ri-error-warning-fill align-middle me-1 fs-22"></i> {{ __('Konfirmo Fshirjen') }}
                        </h5>
                        <button type="button" class="btn-close shadow-none" wire:click="mbyllModalFshirjen"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-light border border-dark border-2 rounded-2 px-4 py-1" style="min-width: 160px; height: 45px;">
                                <span class="fs-20 fw-black text-dark font-monospace text-uppercase">{{ $mjetiFshirje->targa }}</span>
                            </div>
                        </div>
                        <div class="bg-danger bg-opacity-10 border border-danger rounded-3 p-3 text-center">
                            <p class="text-dark fs-13 fw-medium mb-1">{{ __('A je i sigurt që dëshiron ta fshish këtë regjistrim?') }}</p>
                            <p class="text-secondary fs-12 mb-0">{{ __('Ky veprim nuk mund të kthehet mbrapsht.') }}</p>
                        </div>
                    </div>
                    <div class="modal-footer border-top p-3 d-flex justify-content-end gap-2 bg-light bg-opacity-50">
                        <button type="button" class="btn btn-secondary py-2 px-3 fs-13 fw-semibold rounded-3 text-dark border-0 bg-gray bg-opacity-10" wire:click="mbyllModalFshirjen">{{ __('Anulo') }}</button>
                        <button type="button" class="btn btn-danger py-2 px-3 fs-13 fw-semibold rounded-3 text-white" wire:click="fshijMjetin">
                            <i class="ri-delete-bin-line me-1"></i> {{ __('Po, Fshije') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════
          SEKSIONI 3: MJETET E SHËRBYERA (LARGUAR)
         ════════════════════════════════════════ --}}
    <div class="card bg-white border-0 rounded-3 mb-4 mt-5 shadow-sm">
        <div class="card-body p-4">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-3 mb-4 gap-3">
                <div>
                    <h5 class="fs-16 fw-semibold mb-1"><i class="ri-history-line me-1 text-secondary"></i> {{ __('Mjetet e Shërbyera') }}</h5>
                    <p class="text-secondary fs-12 mb-0">{{ __('Mjetet që kanë përfunduar larjen dhe janë larguar.') }}</p>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="btn-group p-1 bg-light rounded-3" role="group">
                        @foreach(['sot' => 'Sot', 'dje' => 'Dje', 'cakto_daten' => 'Cakto Datën'] as $çelesi => $etiketa)
                            <button type="button" wire:click="$set('tabiAktiv', '{{ $çelesi }}')"
                                    class="btn btn-sm rounded-2 px-3 fs-13 fw-medium {{ $tabiAktiv === $çelesi ? 'btn-primary text-white shadow-sm' : 'btn-light border-0 text-secondary' }}">
                                {{ __($etiketa) }}
                            </button>
                        @endforeach
                    </div>

                    @if($tabiAktiv === 'cakto_daten')
                        <input type="date" wire:model.live="dataSpecifike" class="form-control form-control-sm border-secondary border-opacity-25 rounded-3 fs-13 py-1 px-2" style="width: 150px;">
                    @endif
                </div>
            </div>

            @if($totaletLarguar->isNotEmpty())
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="fs-12 text-secondary align-self-center">{{ __('Totali') }} ({{ $mjeteLarguar->count() }} {{ __('mjete') }}):</span>
                    @foreach($totaletLarguar as $t)
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 fs-12 fw-bold rounded-2">
                            {{ number_format($t['total'], 2) }} {{ $t['kodi'] }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="row gx-2 gy-2">
                @forelse($mjeteLarguar as $m)
                    <div class="col-xxl-2 col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6" wire:key="larguar-{{ $m->id }}">
                        <div class="card bg-light border-0 rounded-3 mb-2 shadow-sm">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 fs-10 fw-medium">
                                        <i class="ri-checkbox-circle-fill fs-7 align-middle me-1 text-muted"></i>{{ __('Larguar') }}
                                    </span>
                                    <span class="text-secondary fw-semibold" style="font-size: 11px;">🕒 {{ $m->ikja?->format('H:i') }}</span>
                                </div>

                                <div class="text-center py-2 my-1">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-white border border-secondary border-opacity-50 rounded-2 w-100"
                                         style="height: 40px; cursor: pointer;" wire:click="shfaqDetajet({{ $m->id }})">
                                        <span class="fs-16 fw-bold text-dark font-monospace text-uppercase" style="letter-spacing: 0.5px;">{{ $m->targa }}</span>
                                    </div>
                                </div>

                                <div class="text-center mb-1">
                                    <div class="fs-12 fw-semibold text-dark text-truncate">{{ $m->sherbimi->sherbimi ?? '—' }}</div>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary fs-10 rounded-2 px-2 py-1">{{ $m->kategoria->kategoria ?? '—' }}</span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top border-dark border-opacity-10 pt-1 fs-11 text-secondary">
                                    <span>{{ __('Hyrja') }}: <b>{{ $m->nisja->format('H:i') }}</b></span>
                                    <span class="text-dark fw-bold">{{ number_format($m->vlera, 2) }} {{ $m->monedha->kodi ?? '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center py-5 bg-light bg-opacity-50 rounded-3 border border-dashed">
                            <i class="ri-inbox-archive-line fs-32 text-secondary text-opacity-40"></i>
                            <p class="text-secondary fs-13 mt-2 mb-0">{{ __('Nuk ka asnjë mjet të shërbyer për këtë përzgjedhje.') }}</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
          MODAL: DETAJET E MJETIT TË LARGUAR
         ════════════════════════════════════════ --}}
    @if($mjetiDetaje)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1060;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 bg-white shadow rounded-3">
                    <div class="modal-header border-bottom p-4">
                        <h5 class="modal-title fs-16 fw-semibold text-secondary">
                            <i class="ri-information-line align-middle me-1 text-info fs-20"></i>
                            {{ __('Detajet e Operacionit') }}
                        </h5>
                        <button type="button" class="btn-close shadow-none" wire:click="mbyllDetajet"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-dark text-white border border-dark rounded-2 px-4 py-1" style="min-width: 160px; height: 45px;">
                                <span class="fs-20 fw-black font-monospace text-uppercase" style="letter-spacing: 0.8px;">{{ $mjetiDetaje->targa }}</span>
                            </div>
                        </div>

                        <table class="table table-sm table-borderless fs-13 mb-0">
                            <tbody>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Data/Ora Hyrjes') }}:</td>
                                <td class="text-dark py-2 fw-semibold text-end">{{ $mjetiDetaje->nisja->format('d/m/Y - H:i') }}</td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Data/Ora Daljes') }}:</td>
                                <td class="text-dark py-2 fw-semibold text-end">{{ $mjetiDetaje->ikja ? $mjetiDetaje->ikja->format('d/m/Y - H:i') : '-' }}</td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Kohëzgjatja') }}:</td>
                                <td class="text-danger py-2 fw-bold text-end">
                                    {{ $mjetiDetaje->ikja ? $mjetiDetaje->nisja->diffForHumans($mjetiDetaje->ikja, true) : '-' }}
                                </td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Operatori') }}:</td>
                                <td class="text-dark py-2 fw-semibold text-end">👤 {{ $mjetiDetaje->operatori->name ?? __('I panjohur') }}</td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Kategoria e Mjetit') }}:</td>
                                <td class="text-dark py-2 fw-semibold text-end">{{ $mjetiDetaje->kategoria->kategoria ?? '—' }}</td>
                            </tr>
                            <tr class="border-bottom border-light">
                                <td class="text-secondary py-2 fw-medium">{{ __('Shërbimi') }}:</td>
                                <td class="text-primary py-2 fw-semibold text-end">{{ $mjetiDetaje->sherbimi->sherbimi ?? '—' }}</td>
                            </tr>
                            <tr class="bg-success bg-opacity-10 rounded-2">
                                <td class="text-success py-2 fw-bold ps-2">{{ __('Totali i Paguar') }}:</td>
                                <td class="text-success py-2 fw-bolder text-end pe-2 fs-15">
                                    {{ number_format($mjetiDetaje->vlera, 2) }} {{ $mjetiDetaje->monedha->kodi ?? '' }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer border-top p-3 bg-light bg-opacity-50 d-flex justify-content-between gap-2">
                        <button type="button" class="btn btn-outline-primary py-2 px-3 fs-13 fw-semibold rounded-3" wire:click="perditesoMjetin({{ $mjetiDetaje->id }})">
                            <i class="ri-edit-2-line me-1"></i> {{ __('Edito') }}
                        </button>
                        <button type="button" class="btn btn-secondary py-2 px-3 fs-13 fw-semibold rounded-3 text-dark border-0 bg-gray bg-opacity-20" wire:click="mbyllDetajet">
                            {{ __('Mbyll') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
