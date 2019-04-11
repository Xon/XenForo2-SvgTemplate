# SVG Template

Depending on configuration, this add-on requires webserver URL rewrite support!

Allows SVG (Scalable Vector Graphics) images to be stored as templates. This creates a new svg.php file in the XF root directory.

To generate a link to an SVG template (in a template/less file);
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

## XenForo 2 routing integration

While webserver rewrite rules are recommended, this add-on supports extending XenForo's routing system to provide zero-configuration support for SVG Templates

## Nginx URL rewrite config

```
location ^~ /data/svg/ {
   access_log off;
   rewrite ^/data/svg/([^/]+)/([^/]+)/([^/]+)/([^\.]+).svg$ /svg.php?svg=$4&s=$1&l=$2&d=$3$args last;
   return 403;
}
```

## Apache URL rewrite config

```
#       SVG Support
RewriteRule ^/data/svg/([^/]+)/([^/]+)/([^/]+)/([^\.]+).svg$ /svg.php?svg=$4&s=$1&l=$2&d=$3$args [L]
```

## Requirements

- PHP 5.6 or newer