<?php
class PayPalAddon {
    public static function getKey() { return 'paypal'; }
    public static function getName() { return 'PayPal'; }
    public static function getDescription() { return 'Bezahlmethode PayPal (Beispiel-Addon)'; }
    public static function isEnabled($settings) {
        return !empty($settings['addons']['paypal']['enabled']);
    }
}