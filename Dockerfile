FROM php:8.2-apache

# Activer la réécriture d'URL Apache si nécessaire
RUN a2enmod rewrite

# Copier les fichiers du dépôt dans le dossier web du serveur
COPY . /var/www/html/

# Donner les permissions d'écriture pour le dossier data (sauvegarde des candidatures)
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/

EXPOSE 80
