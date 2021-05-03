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

# Render to PNG

Rendering SVGs to PNGs requires external support, and depending on OS this may result in odd limitations or poor rendering.

## php-imagick support

Ubuntu (using https://launchpad.net/~ondrej/+archive/ubuntu/php PPA);
```
sudo apt install php7.4-imagick libmagickcore-6.q16-3-extra
sudo systemctl restart php7.4-fpm
```
Note; some distro's require libmagickcore-6.q16-3-extra to be installed to enable SVG support.

If PNG rendering looks corrupted or wrong, attempt to use the CLI option, and a different conversation tool

## CLI support

This is a generic escape hatch to plug in arbitrary png conversion, using `proc_open` in php.

Configure `Render using proc_open` option with;
```
<CLI-binary> {destFile} {sourceFile}
```
{sourceFile} is the source SVG written as a temp file
{destFile} is the destination PNG file as a temp file

Alternatively input/output can be done via pipes

Note; template names are only alpha-numeric strings, which is enforced by validation before the CLI option is called

### Librsvg CLI support

```
sudo apt install librsvg2-bin
```

Configure `Render using exec` with;
```
rsvg-convert --background-color=transparent --format=png --output={destFile} {sourceFile}
```
Untick "use pipes"

### Inkscape CLI support

Note; use `snap` as otherwise it is likely to have too old an instance!
```
sudo snap install inkscape
```

Configure `Render using exec` with;
```
inkscape --export-type=png -p
```
Tick "use pipes"

# Features

## Conditional rendering SVGs to PNG (for CSS/LESS)


An example of conditional CSS to use the png over the svg for mobile clients
```
.mod_interrupt--svg.mod_interrupt
{
    &--stop
    {
        &:before
        {
          content: url({{ getSvgUrl('sv_bbcode_modinterrupt_stop.svg') }}) !important;
        }
        <xf:if is="$xf.svg.as.png">
        .is-tablet &:before,
        .is-mobile &:before
        {
          content: url({{ getSvgUrlAs('sv_bbcode_modinterrupt_stop.svg', 'png') }}) !important;
        }
        </xf:if>
    }
}
```

Explicit usage in templates;
```xml
<xf:if is="$xf.svg.enabled">
    <xf:if is="$xf.svg.as.png and $xf.mobileDetect and $xf.mobileDetect.isMobile()">
        <img src="{{ getSvgUrlAs('example.svg', 'png') }}"/>
    <xf:else />
        <img src="{{ getSvgUrlAs('example.svg', 'svg') }}"/>
    </xf:if>
<xf:else />
    <i class="fa fa-stop" />
</xf:if>
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