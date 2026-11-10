{**
 * Quince Offers — przycisk i szuflada oferty na karcie produktu.
 *
 * Układ i nazwy klas są z makiety `strona-produktu-alt.html`: przycisk cichy
 * obok „Zapytaj specjalistę", a pod nim szuflada z listą ofertową, trzema polami
 * i dwoma przyciskami. Pola przepisane na pływające etykiety, bo tak wyglądają
 * dziś wszystkie pola motywu.
 *
 * Styl należy do motywu (`components/offer.css`), zachowanie do wyspy
 * (`islands/offer.js`). Tutaj jest sama treść.
 *}

{* Napisy dla wyspy stoją w znacznikach, a nie w skrypcie: skrypt jest jeden
   dla wszystkich języków, a te zdania tłumaczy sklep. *}
<div class="q-offer__trigger" data-q-offer-root
     data-url="{$quinceOfferUrl}"
     data-id-product="{$quinceOfferProduct|intval}"
     data-label-remove="{l s='Remove from the quote list' d='Modules.Quinceoffers.Shop'}"
     data-msg-email="{l s='Enter your email address.' d='Modules.Quinceoffers.Shop'}"
     data-msg-sent="{l s='The quote has been sent. Its number is' d='Modules.Quinceoffers.Shop'}"
     data-msg-failure="{l s='The quote could not be sent. Please try again in a moment.' d='Modules.Quinceoffers.Shop'}">
  <button type="button" class="q-btn q-btn--secondary q-btn--quiet" data-q-offer-open>
    <svg class="q-btn__icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zm0 1h8a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1"/><path d="M4.603 12.087a.8.8 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.7 7.7 0 0 1 1.482-.645 20 20 0 0 0 1.062-2.227 7.3 7.3 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.187-.012.395-.047.614-.084.51-.27 1.134-.52 1.794a11 11 0 0 0 .98 1.686 5.8 5.8 0 0 1 1.334.05c.364.065.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.86.86 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.7 5.7 0 0 1-.911-.95 11.6 11.6 0 0 0-1.997.406 11.3 11.3 0 0 1-1.021 1.51c-.29.35-.608.655-.926.787a.8.8 0 0 1-.58.029m1.379-1.901q-.25.115-.459.238c-.328.194-.541.383-.647.547-.094.145-.096.25-.04.361q.016.032.026.044l.035-.012c.137-.056.355-.235.635-.572a8 8 0 0 0 .45-.606m1.64-1.33a13 13 0 0 1 1.01-.193 12 12 0 0 1-.51-.858 21 21 0 0 1-.5 1.05zm2.446.45q.226.244.435.41c.24.19.407.253.498.256a.1.1 0 0 0 .07-.015.3.3 0 0 0 .094-.125.44.44 0 0 0 .059-.2.1.1 0 0 0-.026-.063c-.052-.062-.2-.152-.518-.209a4 4 0 0 0-.612-.053zM8.078 5.8a7 7 0 0 0 .2-.828q.046-.282.038-.465a.6.6 0 0 0-.032-.198.5.5 0 0 0-.145.04c-.087.035-.158.106-.196.283-.04.192-.03.469.046.822q.036.167.09.346z"/></svg>
    <span class="q-btn__label">{l s='Download a quote' d='Modules.Quinceoffers.Shop'}</span>
  </button>
</div>

<dialog class="q-modal q-modal--drawer" data-q-offer aria-labelledby="q-offer-title">
  <div class="q-modal__head">
    {* Ikona wewnątrz tytułu i w barwie marki, tak jak w makiecie szuflad
       i w szufladzie powiadomień: nagłówek mówi jednym znakiem, o co chodzi. *}
    <h2 class="q-modal__title" id="q-offer-title">
      <svg class="q-modal__icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5"/><path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z"/></svg>
      {l s='Download a quote' d='Modules.Quinceoffers.Shop'}
    </h2>
    <button class="q-modal__close" type="button" data-q-offer-close
            aria-label="{l s='Close' d='Shop.Theme.Actions'}">
      <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>
    </button>
  </div>

  <div class="q-modal__body">
    <div data-q-offer-form>
    <h3 class="q-drawer__heading">{l s='Ask for a PDF quote' d='Modules.Quinceoffers.Shop'}</h3>
    <p class="q-drawer__lead">{l s='Leave your email address and we will send you a detailed quote in PDF, with net prices, delivery time and warranty terms.' d='Modules.Quinceoffers.Shop'}</p>

    {* Lista ofertowa. Nie jest ozdobą: to ona jest powodem, dla którego niżej
       stoi drugi przycisk. Klient zbiera produkty z kilku kart i dostaje jedną
       ofertę na wszystkie, zamiast trzech osobnych wiadomości. *}
    <div class="q-offer" data-q-offer-box hidden>
      <div class="q-offer__head">
        {l s='Quote list' d='Modules.Quinceoffers.Shop'} (<span data-q-offer-count>0</span>)
      </div>
      <ul class="q-offer__list" data-q-offer-list></ul>
    </div>

    <div class="q-float q-offer__field">
      <label class="form-label required" for="q-offer-email">{l s='Your email address' d='Modules.Quinceoffers.Shop'}</label>
      <input class="q-field form-control" type="email" id="q-offer-email" name="email"
             placeholder=" " autocomplete="email" data-q-offer-email>
    </div>

    <div class="q-float q-offer__field">
      <label class="form-label" for="q-offer-name">{l s='Name or company' d='Modules.Quinceoffers.Shop'}</label>
      <input class="q-field form-control" type="text" id="q-offer-name" name="customer_name"
             placeholder=" " autocomplete="organization" data-q-offer-name>
    </div>

    <div class="q-float q-offer__field">
      <label class="form-label" for="q-offer-phone">{l s='Phone (optional)' d='Modules.Quinceoffers.Shop'}</label>
      <input class="q-field form-control" type="tel" id="q-offer-phone" name="phone"
             placeholder=" " autocomplete="tel" data-q-offer-phone>
    </div>

    {* Pole wyboru motywu, a nie goły znacznik: kwadrat, znak i ognisko wyglądają
       wtedy tak samo jak w kasie i w rejestracji. *}
    <label class="q-choice q-offer__consent">
      <input class="q-choice__input" type="checkbox" data-q-offer-newsletter>
      <span class="q-choice__label">{l s='Sign me up for the newsletter' d='Modules.Quinceoffers.Shop'}</span>
    </label>

    {* Cztery zapory silnika: znacznik strony, nazwa formularza, pułapka i czas
       wypełnienia. Sprawdza je hook silnika, zanim żądanie dojdzie do modułu. *}
    <div data-q-offer-guard>
      {include file='_partials/form-guard.tpl' form_id='quince_offer'}
    </div>

    <p class="q-offer__message" data-q-offer-message aria-live="assertive" hidden></p>
    </div>

    {* Po wysłaniu formularz znika, a na jego miejscu zostaje potwierdzenie
       z numerem. Numer jest jedyną rzeczą, po której klient może się do nas
       odwołać — dlatego stoi osobno, a nie w środku zdania. Tu też stanie
       przycisk „Pobierz PDF", gdy dojdzie generator. *}
    <div class="q-offer__done" data-q-offer-done hidden>
      <svg class="q-offer__done-icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
        <path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/>
      </svg>
      <h3 class="q-offer__done-title">{l s='The quote is on its way' d='Modules.Quinceoffers.Shop'}</h3>
      <p class="q-offer__done-text">
        {l s='We have sent it to' d='Modules.Quinceoffers.Shop'}
        <strong data-q-offer-done-email></strong>
      </p>
      <p class="q-offer__done-number" data-q-offer-done-number></p>
      {* Plik do pobrania od razu, bez czekania na wiadomość. Odnośnik przynosi
         odpowiedź serwera, więc przycisk pojawia się tylko wtedy, gdy plik
         naprawdę powstał. *}
      <a class="q-btn q-btn--secondary" data-q-offer-download hidden href="#" download>
        {l s='Download the PDF' d='Modules.Quinceoffers.Shop'}
      </a>
      <p class="q-offer__done-text">{l s='Keep the number — quote it when you get back to us.' d='Modules.Quinceoffers.Shop'}</p>
    </div>
  </div>

  <div class="q-modal__foot">
    <div class="q-drawer__foot-actions q-drawer__foot-actions--column">
      <button class="q-btn q-btn--primary q-btn--lg q-btn--block" type="button" data-q-offer-send>
        <svg class="q-btn__icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576zm6.787-8.201L1.591 6.602l4.339 2.76z"/></svg>
        <span class="q-btn__label">{l s='Send the quote' d='Modules.Quinceoffers.Shop'}</span>
      </button>
      <button class="q-btn q-btn--secondary q-btn--lg q-btn--block" type="button" data-q-offer-add>
        <svg class="q-btn__icon" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8 7a.5.5 0 0 1 .5.5V9H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V10H6a.5.5 0 0 1 0-1h1.5V7.5A.5.5 0 0 1 8 7"/><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/></svg>
        <span class="q-btn__label">{l s='Add to the quote list' d='Modules.Quinceoffers.Shop'}</span>
      </button>
      <button class="q-btn q-btn--primary q-btn--lg q-btn--block" type="button" data-q-offer-done-close hidden>
        {l s='Close' d='Shop.Theme.Actions'}
      </button>
    </div>
  </div>
</dialog>
