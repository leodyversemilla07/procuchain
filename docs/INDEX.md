# ProcuChain Documentation Index

**Last Updated:** November 15, 2025

Welcome to the ProcuChain documentation! This index will help you navigate the available documentation based on your needs.

---

## Quick Links

| Document | Purpose | Target Audience |
|----------|---------|-----------------|
| [README.md](../README.md) | Project overview, installation, usage | Everyone |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Complete system architecture | Developers, Architects |
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | Database design and structure | Developers, DBAs |
| [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) | Development workflows and standards | Developers |
| [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md) | Production deployment instructions | DevOps, Admins |
| [MONITORING_GUIDE.md](./MONITORING_GUIDE.md) | System monitoring and alerting | DevOps, Admins |

---

## Documentation by Role

### For New Developers

Start here to understand the project:

1. **[README.md](../README.md)** - Project overview and setup
2. **[ARCHITECTURE.md](./ARCHITECTURE.md)** - System architecture
3. **[DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md)** - Development workflow
4. **[DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md)** - Database structure

### For System Administrators

Deployment and maintenance:

1. **[DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md)** - Deployment procedures
2. **[MONITORING_GUIDE.md](./MONITORING_GUIDE.md)** - System monitoring
3. **[DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md)** - Database maintenance

### For Project Managers

High-level understanding:

1. **[README.md](../README.md)** - Project overview and features
2. **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Technology stack and architecture
3. **[stages.md](./stages.md)** - Procurement workflow stages

### For Security Auditors

Security documentation:

1. **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Security Architecture section
2. **[DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md)** - Security & Audit Tables section
3. **[README.md](../README.md)** - Security section

---

## Documentation by Topic

### System Architecture

- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Complete architecture documentation
  - Technology stack
  - Application layers
  - Service architecture
  - Frontend structure
  - Blockchain integration
  - Security architecture

### Database

- **[DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md)** - Comprehensive database documentation
  - Table structures
  - Relationships
  - Indexes and performance
  - Maintenance procedures

### Development

- **[DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md)** - Developer handbook
  - Getting started
  - Code standards
  - Testing guidelines
  - Common tasks
  - Troubleshooting

### Deployment

- **[DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md)** - Deployment procedures
  - Environment setup
  - Configuration
  - Production deployment
  - Post-deployment tasks

### Business Logic

- **[stages.md](./stages.md)** - 15 procurement stages
- **[PROCUREMENT_PHASE_RESTRUCTURE.md](./PROCUREMENT_PHASE_RESTRUCTURE.md)** - Phase organization
- **[TRANSACTION_BOUNDARIES_ARCHITECTURE.md](./TRANSACTION_BOUNDARIES_ARCHITECTURE.md)** - Blockchain transaction design

### Monitoring & Operations

- **[MONITORING_GUIDE.md](./MONITORING_GUIDE.md)** - System monitoring
  - Health checks
  - Alerting
  - Performance monitoring
  - Error tracking

---

## Key Concepts

### Procurement Workflow

ProcuChain implements a **15-stage procurement workflow** following RA 9184 (Government Procurement Reform Act):

1. Procurement Initiation
2. Pre-Procurement Conference
3. Bidding Documents
4. Pre-Bid Conference
5. Supplemental Bid Bulletin
6. Bid Opening
7. Bid Evaluation
8. Post-Qualification
9. BAC Resolution
10. Notice of Award
11. Performance Bond, Contract and PO
12. Notice to Proceed
13. Monitoring
14. Completion
15. Completed

See [stages.md](./stages.md) for details.

### Blockchain Integration

- **8 MultiChain Streams** for organizing data
- **On-chain file storage** (no external storage needed)
- **SHA-256 hashing** for file integrity
- **Immutable audit trail** for all operations

See [ARCHITECTURE.md](./ARCHITECTURE.md#blockchain-integration) for details.

### Role-Based Access Control

**4 Primary Roles:**
- **Admin** - Full system access
- **BAC Secretariat** - Procurement management
- **BAC Chairman** - Oversight
- **HOPE** - Executive monitoring

See [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md#permission-tables) for details.

### Security Features

- **Multi-Factor Authentication** (TOTP)
- **Account Lockout Protection**
- **IP Blocking System**
- **Comprehensive Audit Logging**
- **Device Detection & Tracking**

See [ARCHITECTURE.md](./ARCHITECTURE.md#security-architecture) for details.

---

## Technical Specifications

### Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| **Backend** | Laravel | 12.38.1 |
| **Frontend** | React | 19.2.0 |
| **SPA Framework** | Inertia.js | 2.2.16 |
| **Database** | MySQL | 8.0+ |
| **Blockchain** | MultiChain | 2.3.3+ |
| **CSS Framework** | Tailwind CSS | 4.1.17 |
| **Build Tool** | Vite | 7.1.10 |
| **Testing** | Pest | 4.1.3 |

See [ARCHITECTURE.md](./ARCHITECTURE.md#technology-stack) for complete list.

### System Requirements

- PHP 8.3.27+
- Composer 2.x
- Node.js 18+
- MySQL 8.0+
- MultiChain 2.3.3+

See [README.md](../README.md#requirements) for details.

---

## Contributing

### Code Standards

- **PHP**: PSR-12 + Laravel conventions (Laravel Pint)
- **TypeScript**: ESLint + Prettier
- **Testing**: Pest (feature + unit tests)
- **Documentation**: Markdown

See [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md#code-standards) for details.

### Development Workflow

1. Fork repository
2. Create feature branch
3. Make changes with tests
4. Run code formatters
5. Submit pull request

See [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md#development-workflow) for details.

---

## Support

### Resources

- **GitHub Issues**: https://github.com/leodyversemilla07/procuchain/issues
- **Laravel Documentation**: https://laravel.com/docs/12.x
- **Inertia.js Documentation**: https://inertiajs.com/
- **MultiChain Documentation**: https://www.multichain.com/developers/

### Contact

For questions or support:
- Create an issue on GitHub
- Contact the development team
- Refer to internal wiki

---

## Changelog

### November 15, 2025

- Created comprehensive architecture documentation
- Created database schema documentation
- Created developer guide
- Updated README.md with accurate information
- Created documentation index

### Previous Updates

See [PHASE_IMPLEMENTATION_SUMMARY.md](./PHASE_IMPLEMENTATION_SUMMARY.md) for implementation history.

---

## License

[Add license information]

---

**Documentation Team**  
ProcuChain Development Team
