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

Copy the `.env.example` file to `demo-cv/app/private/.env` and adjust the parameters as required.

The `demo-cv/app/html` directory is the Apache docroot. The `.env`, `config.php` and `setTokens.php`
files live in `demo-cv/app/private`, which sits outside the docroot and is mounted into the
container separately so it can never be served directly.

Finally, run the container using docker compose:

```bash
cd demo-cv/docker
docker compose -p eduwallet_verifier up -d
```

The application can now be accessed at `http://localhost:8080`

## Demo

The Demo application (`demo/`) demonstrates complete credential flows against the live development issuers. It has a PHP back-end (`demo/app`) that holds the API tokens and talks to the issuer, and a Vue 3 + PrimeVue front-end (`demo/frontend`) that only talks to the back-end. See `demo/PLAN.md` for the design and the planned flows. The flows are: deferred credential, multiple credentials, and presentation during issuance (an eduID is presented, optionally issued first, and a Generic Credential is issued from it). The last one needs the embedded authorization server of the sandbox issuer, see the `agents-conf` `development` branch.

### Back-end

Install the dependencies and run the tests (PHP 8.2 and composer):

```bash
cd demo/app
composer install
vendor/bin/phpunit
```

Copy `demo/app/private/.env.example` to `demo/app/private/.env` and set at least `ISSUER_DEV_SANDBOX`. The `private` directory, `src` and `vendor` live outside the Apache docroot `demo/app/html`.

To run it locally, start the container (port 8081):

```bash
cd demo/docker
docker compose up -d --build
```

On `poc5` the container is created by the Ansible deployment.

### Front-end

Node 20 or later is needed to build the front-end. The build goes into the Apache docroot `demo/app/html`, next to `index.php`:

```bash
cd demo/frontend
npm ci
npm test            # unit and component tests
npm run build       # type check and build into ../app/html
```

For development, run the back-end container (port 8081) and then `npm run dev`: the Vite dev server proxies `/api` to it.

