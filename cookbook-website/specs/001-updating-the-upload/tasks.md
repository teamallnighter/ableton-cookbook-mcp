# Tasks: Enhanced Nested Chain Analysis

**Input**: Design documents from `/specs/001-updating-the-upload/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → If not found: ERROR "No implementation plan found"
   → Extract: tech stack, libraries, structure
2. Load optional design documents:
   → data-model.md: Extract entities → model tasks
   → contracts/: Each file → contract test task
   → research.md: Extract decisions → setup tasks
3. Generate tasks by category:
   → Setup: project init, dependencies, linting
   → Tests: contract tests, integration tests
   → Core: models, services, CLI commands
   → Integration: DB, middleware, logging
   → Polish: unit tests, performance, docs
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Same file = sequential (no [P])
   → Tests before implementation (TDD)
5. Number tasks sequentially (T001, T002...)
6. Generate dependency graph
7. Create parallel execution examples
8. Validate task completeness:
   → All contracts have tests?
   → All entities have models?
   → All endpoints implemented?
9. Return: SUCCESS (tasks ready for execution)
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions

## Path Conventions
- **Laravel app structure**: `laravel-app/app/`, `laravel-app/database/`, `laravel-app/tests/`
- **Service layer**: `laravel-app/app/Services/`
- **Models**: `laravel-app/app/Models/`
- **Controllers**: `laravel-app/app/Http/Controllers/Api/`
- **Migrations**: `laravel-app/database/migrations/`

## Phase 3.1: Constitutional & Setup
- [x] T001 **Amend Constitution to establish Analysis Completeness Principle**
  - Update `.specify/memory/constitution.md` to version 1.1.0
  - Add new business rule: "ALL CHAINS within uploaded rack files MUST be detected and included in analysis"
  - Add Analysis Completeness Principle to core requirements
  - Update constitutional compliance checklist in plan template

- [x] T002 **Validate existing test racks in /testRacks directory**
  - Inventory all .adg files in `/testRacks` with documented nested structures
  - Create test rack documentation with expected chain counts and hierarchy
  - Verify test racks are accessible and valid format
  - Document manual inspection results for validation

## Phase 3.2: Tests First (TDD) ⚠️ MUST COMPLETE BEFORE 3.3

### Contract Tests (API Endpoints)
- [x] T003 [P] **Create contract test for POST /racks/{uuid}/analyze-nested-chains**
  - File: `laravel-app/tests/Feature/NestedChainAnalysisApiTest.php`
  - Test endpoint accepts UUID parameter and optional force_reanalysis flag
  - Verify 200/202 responses with proper JSON schemas
  - Test authentication and rate limiting (10/min batch operations)
  - Must fail initially (no implementation yet)

- [x] T004 [P] **Create contract test for GET /racks/{uuid}/nested-chains**
  - File: `laravel-app/tests/Feature/NestedChainHierarchyApiTest.php`
  - Test query parameters: include_devices, max_depth
  - Verify JSON response matches NestedChainHierarchy schema
  - Test pagination and filtering
  - Must fail initially (no implementation yet)

- [x] T005 [P] **Create contract test for GET /racks/{uuid}/nested-chains/{chainId}**
  - File: `laravel-app/tests/Feature/NestedChainDetailApiTest.php`
  - Test specific chain detail retrieval
  - Verify NestedChainDetail schema compliance
  - Test 404 handling for invalid chain IDs
  - Must fail initially (no implementation yet)

- [x] T006 [P] **Create contract test for GET /analysis/constitutional-compliance**
  - File: `laravel-app/tests/Feature/ConstitutionalComplianceApiTest.php`
  - Test compliance report generation with pagination
  - Verify ConstitutionalComplianceReport schema
  - Test filtering and sorting options
  - Must fail initially (no implementation yet)

- [x] T007 [P] **Create contract test for POST /analysis/batch-reprocess**
  - File: `laravel-app/tests/Feature/BatchReprocessApiTest.php`
  - Test batch processing with max 10 racks per request
  - Verify rate limiting enforcement
  - Test priority queue handling
  - Must fail initially (no implementation yet)

### Integration Tests (User Scenarios)
- [x] T008 [P] **Create integration test for Basic Nested Chain Detection (Scenario 1)**
  - File: `laravel-app/tests/Feature/BasicNestedChainDetectionTest.php`
  - Use test rack from /testRacks with known nested structure
  - Test complete workflow: upload → analyze → retrieve → validate
  - Verify database state matches expected hierarchy
  - Test performance: analysis completes within 5 seconds

- [x] T009 [P] **Create integration test for Deep Nesting Validation (Scenario 2)**
  - File: `laravel-app/tests/Feature/DeepNestingValidationTest.php`
  - Test racks with 3+ levels of nesting
  - Verify max_nesting_depth calculations
  - Test depth limits and performance implications
  - Validate all nesting levels captured correctly

- [ ] T010 [P] **Create integration test for Constitutional Compliance (Scenario 3)**
  - File: `laravel-app/tests/Feature/ConstitutionalComplianceTest.php`
  - Test compliance checking and enforcement
  - Verify non-compliant racks identified correctly
  - Test compliance status updates after reprocessing
  - Validate constitutional requirement enforcement

- [ ] T011 [P] **Create integration test for Batch Reprocessing (Scenario 4)**
  - File: `laravel-app/tests/Feature/BatchReprocessingTest.php`
  - Test batch queue processing with multiple racks
  - Verify job queue management and completion
  - Test rate limiting and performance scaling
  - Validate all racks processed successfully

- [ ] T012 [P] **Create integration test for Backward Compatibility (Scenario 5)**
  - File: `laravel-app/tests/Feature/BackwardCompatibilityTest.php`
  - Verify existing rack display functionality preserved
  - Test existing API endpoints maintain format
  - Validate no data loss in existing rack records
  - Test migration reversibility

- [ ] T013 [P] **Create integration test for Error Handling (Scenario 6)**
  - File: `laravel-app/tests/Feature/ErrorHandlingTest.php`
  - Test corrupted/invalid rack files
  - Test extremely large rack files
  - Test empty chain structures and edge cases
  - Verify graceful error handling and user feedback

## Phase 3.3: Database Layer

### Migrations
- [ ] T014 [P] **Create migration for nested_chains table**
  - File: `laravel-app/database/migrations/YYYY_MM_DD_HHMMSS_create_nested_chains_table.php`
  - Implement NestedChain entity schema with all fields and indexes
  - Add foreign key constraints to racks table
  - Include self-referential parent_chain_id relationship
  - Add performance indexes on rack_id, depth_level, parent_chain_id

- [ ] T015 [P] **Create migration for chain_devices table**
  - File: `laravel-app/database/migrations/YYYY_MM_DD_HHMMSS_create_chain_devices_table.php`
  - Implement ChainDevice entity schema with foreign key to nested_chains
  - Add indexes for query performance
  - Include JSON validation for device_parameters field
  - Add cascade delete constraints

- [ ] T016 [P] **Create migration for enhanced_rack_analyses table**
  - File: `laravel-app/database/migrations/YYYY_MM_DD_HHMMSS_create_enhanced_rack_analyses_table.php`
  - Implement EnhancedRackAnalysis entity schema
  - Add one-to-one relationship with racks table
  - Include JSON field for nested_chain_tree with validation
  - Add indexes for constitutional compliance queries

- [ ] T017 **Add enhanced_analysis_complete flag to racks table**
  - File: `laravel-app/database/migrations/YYYY_MM_DD_HHMMSS_add_enhanced_analysis_flag_to_racks.php`
  - Add boolean column for tracking enhanced analysis status
  - Set default to false for existing records
  - Add index for compliance filtering
  - Ensure backward compatibility

### Models
- [ ] T018 [P] **Create NestedChain model**
  - File: `laravel-app/app/Models/NestedChain.php`
  - Implement all relationships: rack, parent chain, child chains, devices
  - Add validation rules for business constraints
  - Include accessors for hierarchy navigation
  - Add scopes for depth filtering and hierarchy queries

- [ ] T019 [P] **Create ChainDevice model**
  - File: `laravel-app/app/Models/ChainDevice.php`
  - Implement relationship to NestedChain
  - Add JSON casting for device_parameters
  - Include validation rules for device types
  - Add scopes for device filtering and search

- [ ] T020 [P] **Create EnhancedRackAnalysis model**
  - File: `laravel-app/app/Models/EnhancedRackAnalysis.php`
  - Implement one-to-one relationship with Rack
  - Add JSON casting for nested_chain_tree
  - Include constitutional compliance checks
  - Add scopes for compliance reporting

- [ ] T021 **Update Rack model for enhanced analysis**
  - File: `laravel-app/app/Models/Rack.php`
  - Add relationship to EnhancedRackAnalysis
  - Add relationship to NestedChains through rack_id
  - Include methods for constitutional compliance checking
  - Add scopes for enhanced analysis filtering

## Phase 3.4: Service Layer

### Core Services
- [ ] T022 **Create NestedChainAnalyzer service**
  - File: `laravel-app/app/Services/NestedChainAnalyzer.php`
  - Implement recursive XPath parsing for nested chains
  - Add depth tracking and circular reference detection
  - Include performance optimization for large files
  - Add logging for debugging and monitoring

- [ ] T023 **Enhance AbletonRackAnalyzer service**
  - File: `laravel-app/app/Services/AbletonRackAnalyzer.php`
  - Integrate NestedChainAnalyzer for comprehensive analysis
  - Add constitutional compliance validation
  - Maintain backward compatibility with existing analysis
  - Add enhanced analysis completion tracking

- [ ] T024 [P] **Create ConstitutionalComplianceService**
  - File: `laravel-app/app/Services/ConstitutionalComplianceService.php`
  - Implement compliance checking and reporting
  - Add methods for identifying non-compliant racks
  - Include batch compliance assessment
  - Add compliance status tracking and updates

- [ ] T025 [P] **Create NestedChainHierarchyService**
  - File: `laravel-app/app/Services/NestedChainHierarchyService.php`
  - Implement hierarchy tree generation and navigation
  - Add methods for depth-limited queries
  - Include device inclusion/exclusion options
  - Add caching for complex hierarchy queries

## Phase 3.5: API Layer

### Controllers
- [ ] T026 **Create NestedChainAnalysisController**
  - File: `laravel-app/app/Http/Controllers/Api/NestedChainAnalysisController.php`
  - Implement POST /racks/{uuid}/analyze-nested-chains endpoint
  - Add authentication and rate limiting (10/min batch operations)
  - Include job queue integration for async processing
  - Add proper error handling and status responses

- [ ] T027 **Create NestedChainController**
  - File: `laravel-app/app/Http/Controllers/Api/NestedChainController.php`
  - Implement GET /racks/{uuid}/nested-chains endpoint
  - Implement GET /racks/{uuid}/nested-chains/{chainId} endpoint
  - Add query parameter validation and filtering
  - Include pagination and performance optimization

- [ ] T028 **Create ConstitutionalComplianceController**
  - File: `laravel-app/app/Http/Controllers/Api/ConstitutionalComplianceController.php`
  - Implement GET /analysis/constitutional-compliance endpoint
  - Add pagination and filtering for compliance reports
  - Include statistical summary generation
  - Add admin-only access controls

- [ ] T029 **Create BatchReprocessController**
  - File: `laravel-app/app/Http/Controllers/Api/BatchReprocessController.php`
  - Implement POST /analysis/batch-reprocess endpoint
  - Add batch size validation (max 10 racks)
  - Include priority queue management
  - Add progress tracking and status reporting

### API Routes
- [ ] T030 **Add nested chain analysis routes to API**
  - File: `laravel-app/routes/api.php`
  - Register all new API endpoints with proper middleware
  - Add rate limiting for analysis endpoints
  - Include authentication requirements
  - Add OpenAPI documentation annotations

## Phase 3.6: Job Queue Integration
- [ ] T031 [P] **Create ProcessNestedChainAnalysisJob**
  - File: `laravel-app/app/Jobs/ProcessNestedChainAnalysisJob.php`
  - Implement background processing for enhanced analysis
  - Add timeout handling and failure recovery
  - Include progress tracking and status updates
  - Add job retry logic for transient failures

- [ ] T032 [P] **Create BatchReprocessJob**
  - File: `laravel-app/app/Jobs/BatchReprocessJob.php`
  - Implement batch processing coordination
  - Add individual rack processing job dispatch
  - Include batch completion tracking
  - Add error aggregation and reporting

## Phase 3.7: D2 Diagram Integration
- [ ] T033 **Enhance D2DiagramService for nested chains**
  - File: `laravel-app/app/Services/D2DiagramService.php`
  - Add hierarchical subgraph generation for nested chains
  - Include expandable node representation
  - Maintain compatibility with existing caching
  - Add ASCII export for nested hierarchy

## Phase 3.8: Testing & Validation

### Performance Tests
- [ ] T034 [P] **Create NestedChainPerformanceTest**
  - File: `laravel-app/tests/Performance/NestedChainPerformanceTest.php`
  - Test analysis time with various rack complexities
  - Verify sub-5 second performance target
  - Test memory usage during deep nesting analysis
  - Validate cache performance improvements

### Unit Tests
- [ ] T035 [P] **Create NestedChainAnalyzer unit tests**
  - File: `laravel-app/tests/Unit/NestedChainAnalyzerTest.php`
  - Test recursive parsing logic
  - Test depth limiting and circular reference detection
  - Test XML path extraction and validation
  - Test error handling for malformed XML

- [ ] T036 [P] **Create ConstitutionalComplianceService unit tests**
  - File: `laravel-app/tests/Unit/ConstitutionalComplianceServiceTest.php`
  - Test compliance checking algorithms
  - Test batch compliance assessment
  - Test status tracking and updates
  - Test reporting and statistics generation

- [ ] T037 [P] **Create NestedChainHierarchyService unit tests**
  - File: `laravel-app/tests/Unit/NestedChainHierarchyServiceTest.php`
  - Test hierarchy tree generation
  - Test depth-limited queries
  - Test device filtering options
  - Test caching behavior

## Phase 3.9: Documentation & Deployment
- [ ] T038 [P] **Update OpenAPI documentation**
  - File: `laravel-app/storage/api-docs/api-docs.json`
  - Generate updated API documentation with new endpoints
  - Include schema definitions for new response types
  - Add authentication and rate limiting documentation
  - Validate against contract specifications

- [ ] T039 **Update CLAUDE.md with enhanced analysis features**
  - File: `laravel-app/CLAUDE.md`
  - Document new constitutional requirement
  - Add enhanced analysis workflow guidance
  - Include troubleshooting for nested chain issues
  - Update development commands and testing procedures

## Dependency Graph

### Phase Dependencies
1. **Constitutional & Setup** (T001-T002) → Must complete before all other phases
2. **Tests** (T003-T013) → Must complete before implementation phases
3. **Database** (T014-T021) → Required for Service and API layers
4. **Services** (T022-T025) → Required for API layer
5. **API** (T026-T030) → Depends on Services and Database
6. **Integration** (T031-T033) → Depends on all core components
7. **Testing & Validation** (T034-T037) → Can run parallel with implementation
8. **Documentation** (T038-T039) → Final phase after implementation

### Critical Path
T001 → T014,T015,T016,T017 → T018,T019,T020,T021 → T022,T023 → T026,T027 → T030 → T038

## Parallel Execution Examples

### Round 1: Contract Tests (After Constitutional Amendment)
```bash
# These can run in parallel - different test files
laravel-app/tests/Feature/NestedChainAnalysisApiTest.php     # T003
laravel-app/tests/Feature/NestedChainHierarchyApiTest.php    # T004
laravel-app/tests/Feature/NestedChainDetailApiTest.php       # T005
laravel-app/tests/Feature/ConstitutionalComplianceApiTest.php # T006
laravel-app/tests/Feature/BatchReprocessApiTest.php         # T007
```

### Round 2: Integration Tests
```bash
# These can run in parallel - different test files
laravel-app/tests/Feature/BasicNestedChainDetectionTest.php  # T008
laravel-app/tests/Feature/DeepNestingValidationTest.php      # T009
laravel-app/tests/Feature/ConstitutionalComplianceTest.php   # T010
laravel-app/tests/Feature/BatchReprocessingTest.php         # T011
laravel-app/tests/Feature/BackwardCompatibilityTest.php     # T012
laravel-app/tests/Feature/ErrorHandlingTest.php             # T013
```

### Round 3: Database Migrations
```bash
# These can run in parallel - different migration files
laravel-app/database/migrations/*_create_nested_chains_table.php     # T014
laravel-app/database/migrations/*_create_chain_devices_table.php     # T015
laravel-app/database/migrations/*_create_enhanced_rack_analyses_table.php # T016
```

### Round 4: Models
```bash
# These can run in parallel - different model files
laravel-app/app/Models/NestedChain.php          # T018
laravel-app/app/Models/ChainDevice.php          # T019
laravel-app/app/Models/EnhancedRackAnalysis.php # T020
```

### Round 5: Independent Services
```bash
# These can run in parallel - different service files
laravel-app/app/Services/ConstitutionalComplianceService.php  # T024
laravel-app/app/Services/NestedChainHierarchyService.php      # T025
```

## Task Validation Checklist
- [x] All API contracts have corresponding test tasks (T003-T007)
- [x] All entities have model creation tasks (T018-T020)
- [x] All endpoints have implementation tasks (T026-T029)
- [x] All user scenarios have integration tests (T008-T013)
- [x] Constitutional compliance addressed (T001, T010, T024, T028)
- [x] Performance requirements covered (T034)
- [x] Backward compatibility ensured (T012, T021, T023)
- [x] D2 integration maintained (T033)
- [x] Documentation updated (T038-T039)

**Total Tasks**: 39 tasks across 9 phases
**Parallel Tasks**: 20 tasks can run in parallel (marked with [P])
**Sequential Tasks**: 19 tasks must run in dependency order
**Estimated Completion**: 6-8 weeks with proper task parallelization