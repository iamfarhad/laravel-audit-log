# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Fixed

### Documentation

## [1.3.0] - 2025-06-06

### Added
- Source tracking functionality for audit logs
  - Added `source` column to audit tables to track origin of changes
  - Automatic detection of console commands, HTTP routes, and background jobs
  - Enhanced debugging and compliance capabilities
- New query scopes for source filtering:
  - `forSource(string $source)` - Filter by exact source match
  - `fromConsole()` - Filter for logs from console commands
  - `fromHttp()` - Filter for logs from HTTP requests
  - `fromCommand(string $command)` - Filter by specific console command
  - `fromController(?string $controller = null)` - Filter by controller
- Enhanced query scopes for better audit log filtering
- Improved code quality and static analysis

### Changed
- **BREAKING**: Replaced event-driven architecture with direct logging for better performance
  - Removed `ModelAudited` event dispatching
  - AuditBuilder now uses direct service calls instead of events
  - Improved performance by eliminating event dispatch overhead
- Enhanced `getSource()` method to properly detect console commands from `$_SERVER['argv']`
- Updated `EloquentAuditLog` model to include `source` in fillable attributes
- Improved test coverage and architecture
- Updated code structure and organization
- Enhanced documentation with better examples

### Fixed
- Fixed event function calls with incorrect named parameters
- Resolved database schema issues in test environment
- Fixed PHP deprecation warnings for nullable parameters
- Enhanced source detection for various application contexts
- Fixed code style issues
- Improved SQL query optimization
- Resolved various minor bugs and inconsistencies

### Documentation
- Completely updated README.md to reflect new architecture
- Removed outdated event-driven examples
- Added comprehensive source tracking documentation
- Enhanced query examples with new scopes
- Added database schema documentation
- Improved troubleshooting section with source-related guidance

## [1.2.1] - 2025-05-30

### Added
- Custom event support for manual audit logging
- Enhanced fluent API for custom audit actions

### Changed
- Improved event handling mechanism

## [1.2.0] - 2025-05-30

### Changed
- **BREAKING**: Major structural changes to improve architecture
  - Moved `AuditLog` from Models to DTOs namespace
  - Redesigned `MySQLDriver` with simplified implementation
  - Refactored `AuditLogger` service for better performance
  - Updated `Auditable` trait with improved functionality

### Added
- New `EloquentAuditLog` model with comprehensive scopes
- Enhanced test suite with better coverage:
  - `AuditLogBatchTest` for batch processing
  - `AuditLogIntegrationTest` for integration scenarios
  - `CustomAuditActionTest` for custom actions
  - `AuditLoggerServiceTest` for service testing
  - `AuditableTraitTest` for trait functionality
  - `CauserResolverTest` for user identification
  - `MySQLDriverTest` for driver functionality
- Improved `User` mock with enhanced audit capabilities

### Removed
- Legacy test files that were replaced with new architecture
- Outdated feature tests that didn't align with new structure

### Fixed
- Improved error handling and validation
- Enhanced performance with optimized queries
- Better memory management for large datasets

## [1.1.0] - 2025-05-28

### Added
- Laravel 10.x support
- PHP 8.1 compatibility
- Enhanced framework support for modern Laravel versions

### Removed
- Unused Docker configuration files

### Changed
- Updated composer dependencies for Laravel 10.x
- Improved compatibility matrix

## [1.0.0] - 2025-05-28

### Added
- Initial release of Laravel Audit Logger
- Entity-specific audit tables for optimized performance
- Comprehensive change tracking for CRUD operations
- Customizable field logging with include/exclude options
- User tracking with automatic causer identification
- Event-driven architecture for extensible audit logging
- Batch processing support for high-performance scenarios
- Type safety with PHP 8.1+ strict typing
- MySQL driver implementation
- Automatic migration support for audit tables
- PSR-12 coding standards compliance
- Comprehensive test suite
- Full documentation and README

### Features
- `Auditable` trait for easy model integration
- Fluent API for custom audit events
- Configurable global field exclusions
- Support for custom metadata
- Relationship support for audit logs
- Query scopes for efficient log retrieval
- Batch operations for performance optimization

[Unreleased]: https://github.com/iamfarhad/laravel-audit-log/compare/1.3.0...HEAD
[1.3.0]: https://github.com/iamfarhad/laravel-audit-log/compare/1.2.1...1.3.0
[1.2.1]: https://github.com/iamfarhad/laravel-audit-log/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/iamfarhad/laravel-audit-log/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/iamfarhad/laravel-audit-log/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/iamfarhad/laravel-audit-log/releases/tag/1.0.0
