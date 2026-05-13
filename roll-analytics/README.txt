IMDbRoll Roll Analytics

Bu klasör PHP + SQLite ile basit Roll log tutar.

Dosyalar:
- track.php: Site Roll eventlerini buraya POST eder.
- admin.php: Roll kayıtlarını gösteren panel.
- data/rolls.sqlite: İlk event gelince otomatik oluşur.

Admin panel:
roll-analytics/admin.php?token=bekcf2yngt4pz9urhd8vwsmij60xoa3q

Notlar:
- Hosting PHP ve PDO SQLite desteklemeli.
- data klasörü yazılabilir olmalı.
- Token'ı değiştirmek için config.php içindeki admin_token değerini değiştir.

