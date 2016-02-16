## Installation

cd /var/www/html
git clone git@github.com:iamcal/updog.report.git updog.report
ln -s /var/www/html/updog.report/httpd.conf /etc/apache2/sites-available/updog.report.conf
a2ensite updog.report
service apache2 reload

