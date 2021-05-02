# SVG Template

Depending on configuration, this add-on requires webserver URL rewrite support!

Allows SVG (Scalable Vector Graphics) images to be stored as templates. This creates a new svg.php file in the XF root directory.

To generate a link to an SVG template (The template must have .svg at the end of the name!) ;
```
{{ getSvgUrl('tempate.svg') }}
```

Under Board information, if "Use Full Friendly URLs" (useFriendlyUrls) is set the URL generated is:
```
/data/svg/<style_id>/<langauge_id>/<style_last_modified>/<templateName.svg>
```
Otherwise
```
svg.php?svg=<templateName>&s=<style_id>&l=<langauge_id>&d=<style_last_modified>
```

## XenForo 2 routing integration

While webserver rewrite rules are recommended, this add-on supports extending XenForo's routing system to provide zero-configuration support for SVG Templates

## Nginx URL rewrite config

```
location ^~ /data/svg/ {
   access_log off;
   rewrite ^/data/svg/([^/]+)/([^/]+)/([^/]+)/([^\.]+\..*)$ /svg.php?svg=$4&s=$1&l=$2&d=$3$args last;
   return 403;
}
```

## Apache URL rewrite config

Add the rule before the final index.php;
```
    RewriteRule ^data/svg/([^/]+)/([^/]+)/([^/]+)/([^\.]+\..*)$ svg.php?svg=$4&s=$1&l=$2&d=$3 [B,NC,L,QSA]
```


ie, should look similar to;
```
    #    If you are having problems with the rewrite rules, remove the "#" from the
    #    line that begins "RewriteBase" below. You will also have to change the path
    #    of the rewrite to reflect the path to your XenForo installation.
    #RewriteBase /xenforo


    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -l [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^.*$ - [NC,L]
    RewriteRule ^(data/|js/|styles/|install/|favicon\.ico|crossdomain\.xml|robots\.txt) - [NC,L]
    RewriteRule ^data/svg/([^/]+)/([^/]+)/([^/]+)/([^\.]+\..*)$ svg.php?svg=$4&s=$1&l=$2&d=$3 [B,NC,L,QSA]
    RewriteRule ^.*$ index.php [NC,L]
```

## Requirements

- XenForo 2.1+
- PHP 7.0+ or newer