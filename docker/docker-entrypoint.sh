#!/bin/bash

cd /var/www

export COMPOSER_MEMORY_LIMIT=-1

composer trailcast-docker

bin/console cache:clear

exec "$@"
