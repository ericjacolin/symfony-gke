<VirtualHost *:*>
    DocumentRoot /var/www/sf/public
    ServerName {{ .Env.ROUTER_REQUEST_CONTEXT_HOST }}
    ServerAdmin support@myproject.com
    <Directory /var/www/sf/public>
        AllowOverride AuthConfig
        Require all granted
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
</VirtualHost>
