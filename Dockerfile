# Gunakan base image PHP dengan Apache
FROM php:8.2-apache

# Salin semua file proyek ke dalam container
COPY . /var/www/html/

# Berikan izin akses ke folder aplikasi
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 untuk server
EXPOSE 80
