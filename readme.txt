=== ALStrive ads shortcodes ===
Stable tag: trunk
Tested up to: 6.9.1
Contributors: zchk0
Tags: ads, shortcodes, banners
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Плагин для WordPress, который позволяет создавать группы рекламных материалов и выводить их через шорткоды с ротацией.

Основные возможности:
- Группы рекламных блоков с ротацией по времени или случайным показом при загрузке страницы.
- Элементы двух типов: HTML/JS код или картинка с ссылкой.
- Фильтрация по странам (код из `$_SERVER['GEOIP_COUNTRY_CODE']`).
- Контроль показа на мобильных устройствах.
- Гибкая настройка размеров и стилей изображения, включая мобильные профили.

== Installation ==
1. Загрузите папку плагина в `wp-content/plugins/`.
2. Активируйте плагин в админке WordPress.

== Usage ==
1. Создайте группу в разделе «Рекламные шорткоды» и настройте ротацию.
2. Создайте элементы в разделе «Рекламные материалы», привяжите их к группе и включите.
3. Вставьте шорткод в контент или виджет.

Шорткод:
- `[ad_block id="123"]`
- `[ad_block slug="my-group"]`
- Можно добавить CSS-класс: `[ad_block id="123" class="my-class"]`

Вывод на фронтенде:
- `div.abs-ad-block` с `data-abs-group-id`, `data-abs-item-id`, `data-abs-interval`, `data-abs-rotation-type`.
- Внутри обёртки `div.abs-ad-inner` с HTML выбранного элемента.

Ротация:
- `time`: элементы обновляются через AJAX с указанным интервалом. При смене обновляется `data-abs-item-id`.
- `page_random`: случайный элемент выбирается один раз при загрузке страницы, без автообновления.

Фильтры показа:
- Выводятся только активные элементы.
- Для мобильных устройств учитывается флаг «Рендерить блок на мобильных устройствах».
- Если указаны коды стран, элементы показываются только для них.

== GEOIP_COUNTRY_CODE ==
Плагин читает код страны из `$_SERVER['GEOIP_COUNTRY_CODE']`. Если переменная не задана, фильтр по странам не применяется.

Нужно настроить веб‑сервер так, чтобы он определял страну по IP и прокидывал код в окружение PHP.

Пример для Nginx + `geoip2` (GeoLite2-Country):
```
geoip2 /etc/nginx/GeoLite2-Country.mmdb {
    $geoip2_data_country_code default=US source=$remote_addr country iso_code;
}

server {
    # ...
    location ~ \.php$ {
        # ...
        fastcgi_param GEOIP_COUNTRY_CODE $geoip2_data_country_code;
    }
}
```

Для Apache используйте модуль GeoIP/MaxMind, который выставляет код страны в переменную окружения, и смэппьте её в `GEOIP_COUNTRY_CODE` (см. документацию модуля).

== Changelog ==
- Описание обновлено.
