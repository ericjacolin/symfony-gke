defaults
auth           on
tls            on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
logfile        /var/www/var/log/msmtp.log

# Your mail provider here
account        ovh
host           pro1.mail.ovh.net
auth           on
port           587
from           support@myproject.com
user           support@myproject.com
passwordeval   "echo $MAILER_PASSWORD"

# Set a default account
account default : ovh
