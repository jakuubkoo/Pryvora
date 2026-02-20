# Pryvora

> **Pryvora is a self-hosted, privacy-first personal management platform that securely organizes tasks, notes, calendar events and email workflows.**

---

## What is Pryvora?

Pryvora is not a simple todo app or an email client.

It is a central control panel for personal data — designed to replace scattered tools with a single private system.

The goal:

> Keep sensitive personal information private while still searchable, structured and usable.

### Pryvora combines
- task management
- encrypted notes
- personal planning (calendar)
- email triage and reminders
- document storage

All in a single self-hosted environment.

---

## Why Pryvora Exists

Modern productivity tools store highly sensitive information:
- conversations
- invoices
- reminders
- credentials
- private notes

Most solutions require trusting third-party servers.

Pryvora is built with a different assumption:

> A database breach must not expose readable user data.

All sensitive content is encrypted at rest.

---

## Core Principles

### Privacy First
Sensitive data is encrypted before storage.  
Database access alone must not reveal readable information.

### Self-Hosted
You own the server, the database and the backups.

### One Central Hub
Tasks, notes, email and planning belong together.

### Usable Security
Security should not reduce usability.  
Data remains searchable and structured.

---

## Main Features (Planned)

### Tasks
- inbox tasks
- due dates
- priorities
- recurring tasks
- today dashboard

### Notes
- markdown notes
- tagging
- encrypted storage

### Calendar
- personal planning
- reminders
- Google Calendar integration (future)

### Email Organizer
- Gmail integration
- categorize emails
- reply reminders
- email → task conversion

### Documents
- upload files
- attach to tasks or emails
- encrypted storage

### Notifications
- Telegram notifications
- email alerts
- daily summary

---

## Security Model

Sensitive data is encrypted using **AES-256-GCM authenticated encryption**.

Encrypted:
- notes
- task descriptions
- email body
- attachments
- stored credentials

Not encrypted:
- login email
- password hashes
- minimal metadata for filtering

Passwords are hashed using **Argon2id**.

If an attacker obtains a database dump:
> the content should remain unreadable.

---

## Search

Encrypted content cannot be directly searched.

Pryvora uses a hybrid model:
- encrypted primary storage (PostgreSQL)
- searchable token index (Elasticsearch)

The database stores ciphertext.  
The search index stores only extracted tokens, not full documents.

---

## Architecture

Backend:
- Symfony API

Frontend:
- React

Infrastructure:
- Docker
- PostgreSQL
- Redis
- Elasticsearch
- Background workers (queues)

---

## Project Status

Pryvora is currently under active development.

The project follows incremental releases:
- `0.x` unstable development
- `1.0` daily-usable system

---

## Roadmap

Near goals:
- authentication + 2FA
- encryption core
- notes module
- tasks module
- dashboard

Later:
- email triage
- notifications
- document storage
- AI assistant

---

## Self-Hosting (planned)

Detailed installation instructions will be added once the base system becomes usable.

The project is designed to run using Docker Compose on a VPS or local server.

---

## Philosophy

Pryvora is not trying to compete with large SaaS platforms.

It is designed for people who want:
- control over their data
- privacy
- a unified personal system

---

## License
MIT (planned)
