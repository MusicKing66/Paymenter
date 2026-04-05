<?php

namespace App\Livewire\Components;

use App\Classes\Cart;
use App\Livewire\Component;
use App\Models\Currency;

class LocaleSwitch extends Component
{
    public $currentLocale;

    public $currentCurrency;

    protected $currencies = [];

    public function mount()
    {
        $this->currentLocale = session('locale', config('app.locale'));
        $this->currentCurrency = session('currency', config('settings.default_currency'));
        $this->currencies = Currency::query()->where('code', 'USD')->get()->map(fn ($currency) => [
            'value' => $currency->code,
            'label' => $currency->name,
        ])->values()->toArray();

        if (!collect($this->currencies)->pluck('value')->contains($this->currentCurrency) && count($this->currencies) > 0) {
            $this->currentCurrency = $this->currencies[0]['value'];
            session(['currency' => $this->currentCurrency]);
        }

        if (!in_array($this->currentLocale, $this->allowedLocales(), true)) {
            $this->currentLocale = 'en';
            session(['locale' => $this->currentLocale]);
            app()->setLocale($this->currentLocale);
        }

        if ((count($this->currencies) <= 1 || Cart::items()->count() > 0) && count($this->allowedLocales()) <= 1) {
            $this->skipRender();
        }
    }

    public function updatedCurrentCurrency($currency)
    {
        $allowedCurrencies = collect($this->currencies)->pluck('value')->all();
        $this->validate([
            'currentCurrency' => 'required|in:' . implode(',', $allowedCurrencies),
        ]);
        if (Cart::items()->count() > 0) {
            $this->notify('You cannot change the currency while there are items in the cart.', 'error');
            $this->currentCurrency = session('currency', config('settings.default_currency'));

            return;
        }
        session(['currency' => $currency]);
        $cart = Cart::get();
        if ($cart->exists) {
            $cart->currency_code = $currency;
            $cart->save();
        }

        return $this->redirect(request()->header('Referer', '/'), navigate: true);
    }

    public function updatedCurrentLocale($locale)
    {
        if (!in_array($locale, $this->allowedLocales(), true)) {
            $this->notify('The selected language is not available.', 'error');

            return;
        }

        session(['locale' => $locale]);
        app()->setLocale($locale);

        return $this->redirect(request()->header('Referer', '/'), navigate: true);
    }

    public function render()
    {
        $locales = $this->allowedLocales();
        $localeLabels = [
            'en' => 'English',
            'zh_CN' => '中文',
        ];

        return view('components.locale-switch', compact('locales', 'localeLabels'));
    }

    protected function allowedLocales(): array
    {
        return ['en', 'zh_CN'];
    }
}
