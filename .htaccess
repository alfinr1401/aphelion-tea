# Aphelion Tea — Apache Config
Options -Indexes

# PHP session cookie security
php_flag session.cookie_httponly On
php_flag session.use_strict_mode On

# Allow CORS preflight
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
</IfModule>
