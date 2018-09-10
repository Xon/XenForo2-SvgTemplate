# SVG Template

Depending on configuration, this add-on requires webserver URL rewrite support!

Allows SVG (Scalable Vector Graphics) images to be stored as templates. This creates a new svg.php file in the XF root directory.

To generate a link to an SVG template;
```
{{ getSvgUrl('tempate.svg') }}
```

Under Board information, if "Use Full Friendly URLs" (useFriendlyUrls) is set the URL generated is:
```
/data/svg/<style_id>/<langauge_id>/<style_last_modified>/<templateName>.svg?k=......
```
Otherwise
```
svg.php?svg=<templateName>&s=<style_id>&l=<langauge_id>&d=<style_last_modified>&k=<key>
```


## Enable Template helper to work inside style properties in templates (which are no CSS/SVG)

This is disabled by default.

Under performance Options check "General SVG Template Style Properties "

## Nginx URL rewrite config

```
location ^~ /data/svg/ {
   access_log off;
   rewrite ^/data/svg/([^/]+)/([^/]+)/([^/]+)/([^\.]+).svg$ /svg.php?svg=$4&s=$1&l=$2&d=$3 last;
   return 403;
}
```

## Requirements

- PHP 5.6 or newer