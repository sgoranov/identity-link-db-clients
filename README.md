# Identity Link DB Clients

![License](https://img.shields.io/github/license/sgoranov/identity-link-db-clients)
![Issues](https://img.shields.io/github/issues/sgoranov/identity-link-db-clients)
[![PHPUnit Tests](https://github.com/sgoranov/identity-link-db-clients/actions/workflows/phpunit.yml/badge.svg)](https://github.com/sgoranov/identity-link-db-clients/actions/workflows/phpunit.yml)
[![Security Audit](https://github.com/sgoranov/identity-link-db-clients/actions/workflows/vulnerability-scan.yml/badge.svg)](https://github.com/sgoranov/identity-link-db-clients/actions/workflows/vulnerability-scan.yml)

The client registry of the Identity Link system. This service exposes a REST API for managing
OAuth 2.0 **clients**, their **secrets**, and the **groups** that grant clients scopes on
protected-resource audiences. Other Identity Link services use it to authenticate clients and
to resolve the scopes a client is allowed to request.

## Contents

- [Domain model](#domain-model)
- [Running the service](#running-the-service)
- [Configuration](#configuration)
- [Authentication and scopes](#authentication-and-scopes)
- [OpenAPI specification](#openapi-specification)
- [Console commands](#console-commands)
- [Running the tests](#running-the-tests)
- [License](#license)

## Domain model

| Entity     | Description                                                                                                                                                                                                 |
|------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Client** | An OAuth 2.0 client: unique name, audience, redirect URIs, allowed grant types, public/confidential flag, consent requirement, and optional application, terms of service, privacy policy, and logo URLs. |
| **Secret** | A client credential. Passwords are stored hashed and each secret has an expiration date.                                                                                                                    |
| **Group**  | A named set of clients. A client can belong to several groups.                                                                                                                                              |
| **Scope**  | A scope granted to a group for one audience. A client's scopes for an audience are the union of the scopes of all its groups for that audience.                                                            |

Clients, groups, and secrets flagged as **system** entities cannot be updated or deleted through the API.

## Running the service

### With Docker Compose (recommended)

The service is designed to run as part of the full Identity Link stack. See
[identity-link-docker](https://github.com/sgoranov/identity-link-docker) for Compose files that start it
together with PostgreSQL, Redis, and the other Identity Link services, for both production and local development.

## Configuration

The configuration variables and their defaults are described in the [.env](.env) file. Credentials are read from
[Docker secrets](https://docs.docker.com/compose/how-tos/use-secrets/) when the secret files exist; otherwise
the fallback environment variables `APP_SECRET`, `DB_USER`, and `DB_PASSWORD` are used.

Logs are written to stderr; in `prod` they are formatted as JSON.

## Authentication and scopes

Every endpoint under `/api` except `/api/v1/ping` and `/api/v1/openapi.yaml` requires a JWT bearer token:

```
Authorization: Bearer <access token>
```

Tokens are verified against the keys published at `JWT_JWKS_URI`, and both the issuer and the audience must
equal `JWT_URL`. The token's space-separated `scope` claim must contain the scope the endpoint requires;
otherwise the request is rejected with `403 Forbidden`.

## OpenAPI specification

All endpoints, with their request and response schemas, are described in the OpenAPI specification at
[docs/openapi.yaml](docs/openapi.yaml). You can browse it with [Swagger Editor](https://editor.swagger.io/).
In the `dev` environment the service also serves it at `/api/v1/openapi.yaml`.

The specification is generated from the OpenAPI attributes in `src/`. After changing an endpoint, regenerate it with:

```bash
./vendor/bin/openapi --output docs/openapi.yaml src/
```

## Console commands

Two commands help bootstrap clients without going through the API. Run them inside the container, for example
with `docker compose exec identity-link-db-clients php bin/console …`.

### `app:configure-group-scopes`

Creates the group if it does not exist and **replaces** its scopes for the given audience with the ones provided.
Scopes for other audiences are left untouched.

```bash
php bin/console app:configure-group-scopes \
    --group=backend-services \
    --audience=https://api.example.com \
    --scope=orders.read \
    --scope=orders.write
```

### `app:create-client`

Creates a confidential client, attaches it to existing groups, and generates a secret that expires in one year.
The client ID and secret are printed once and cannot be retrieved later.

```bash
php bin/console app:create-client \
    --name=billing-service \
    --description="Billing backend" \
    --audience=https://api.example.com \
    --group=backend-services
```

| Option           | Required | Description                                                         |
|------------------|----------|---------------------------------------------------------------------|
| `--name`         | yes      | Unique client name.                                                 |
| `--description`  | yes      | Client description.                                                 |
| `--audience`     | yes      | HTTP or HTTPS resource-server audience.                             |
| `--group`        | yes      | Name of an existing group. Repeat to attach several groups.         |
| `--redirect-uri` | no       | Redirect URI. Repeatable. Defaults to the audience.                 |
| `--grant-type`   | no       | OAuth 2.0 grant type. Repeatable. Defaults to `client_credentials`. |

## Running the tests

The test suite runs in Docker against a disposable PostgreSQL instance, so the only requirement is Docker with
the Compose plugin.

```bash
bin/test-run
```

This builds the `test` image, runs PHPUnit, and removes the containers and volumes afterwards. The script exits with
PHPUnit's exit code, and CI runs the same script on every push and pull request to `develop`.

To debug tests interactively, open a shell in the test container with the project directory mounted at `/app`:

```bash
bin/test-debug
# inside the container:
vendor/bin/phpunit --filter ClientControllerTest
```

Xdebug is installed in the test image.

## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse,
modification, and distribution with minimal restrictions.
