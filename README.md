# verifier-deploy

The Verifier Deploy repository hosts a set of credential verifier applications and plugins.

## Demo-CV

The Demo Credential Verifier is an application to demonstrate the possibility of verification of an AcademicBaseCredential (ABC) or a Personal ID (PID).

The application runs in a php-apache docker container and has a front-end facing interface with a back-end server interface that takes care of the actual interaction with the remote Veramo Verifier agent. This hides the Veramo Verifier administrative access token from being exposed in the front end calls.

### Install

Install the Demo Credential Verifier by first building the docker container.

```bash
cd demo-cv/docker
docker compose -p eduwallet_verifier build
cd ../..
```

Install the dependent composer files using the right version of PHP and composer through the docker container:

```bash
cd demo-cv/app
docker run -v .:/var/www/html --entrypoint /usr/local/bin/php eduwallet_verifier-democv -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
docker run -v .:/var/www/html --entrypoint /usr/local/bin/php eduwallet_verifier-democv composer-setup.php
docker run -v .:/var/www/html --entrypoint /usr/local/bin/php eduwallet_verifier-democv composer.phar install
cd ../..
```

Please note that this creates files and directories in the app directory owned by the container root user.

Copy the `.env.example` file to `.env` and adjust the parameters as required.

Finally, run the container in the app folder:

```bash
cd demo-cv/app
docker run -v .:/var/www/html -p 8080:80 eduwallet_verifier-democv
```

The application can now be accessed at `http://localhost:8080`

