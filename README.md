# Dependency File Scaner

This project provide a small backend for handling dependency file uploads, running scans, and reacting with a simple rule engine. The service emulate parts of a CI pipeline where security scans happens after user uploads a lock file.

## Quick Start

1. Install dependancies with `composer install`.
2. Copy `.env` to `.env.local` and ajust secrets like database and API tokens.
3. Boot the docker stack using `docker-compose up -d`.
4. Run migrations with `php bin/console doctrine:migrations:migrate`.

## Core Features

- Upload API that stores package manifests and enqueue processing jobes.
- Debricked client that fake the upload and poll of scan status for each file.
- Rule engine that checks high vulnerability counts, failed uploads, and stuck uploads automaticly.
- Notification service that tries to send email and slack alerts (use Mailhog + fake webhook during dev).

## Testing Notes

There is no big test suite bundle in the repo yet. You can run the console command `php bin/console app:rules:run` to simulate evaluations. Basic scenarious should be covered manualy for now.

## Endpoints Glance

- `POST /api/uploads` to upload one or more files together with email/slack metadata.
- `GET /api/uploads` for pagination over all uploads.
- `GET /api/uploads/{id}` to inspect a single upload status.
- `GET /api/repository/{id}/scans` to list scans tied to an upload.

Enjoy hacking on it and please remmember to wire the Debricked token before hitting the real API.
