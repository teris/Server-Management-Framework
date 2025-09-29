# Shop-Modul – TODO-Liste

- [ ] Bestätigte Bestellungen automatisch in Kundenbestellungen anlegen
  - Beim Statuswechsel auf "paid" automatisiert Kundenauftrag/-vertrag erzeugen (inkl. Referenz zu `customer_id`).
  - Optionale nachgelagerte Provisionierung anstoßen (Platzhalter-Hooks).

- [ ] Bestell-/Konto-Informationen im Frontend-Dashboard einbinden
  - Im `public/dashboard.php` Überblick über letzte Bestellungen, Status, Summen anzeigen.
  - Link zu Bestelldetails einfügen.

- [ ] API-Kommunikation prüfen
  - Robustheit für Endpunkte `ordering/cart.php` und `ordering/order.php` (Auth, Session, JSON-Fehlerbehandlung).
  - Fehler-Logging und klare Fehlercodes ergänzen.

- [ ] Erste Bezahlmethode: Überweisung (Vorkasse)
  - Addon-Struktur erweitern (`addons/banktransfer`) mit Konfiguration (Empfänger, IBAN, BIC, Verwendungszweck `{order_number}`).
  - Auswahl im Checkout anbieten; E-Mail-Template um Zahlungsanweisung ergänzen.

- [ ] Shop auf Sprachmodell umstellen (lang odener mit entsprechenden XML datein erzeugen)

---

Hinweise
- E-Mail-Templates werden optional über das vorhandene System geladen.
- Template-Variablen verfügbar:
  - `{order_number}` – Bestellnummer
  - `{order_total}` – formatierte Gesamtsumme
  - `{order_items}` – HTML-Liste der Positionen
  - `{site_name}` – Seiten-/Shopname


