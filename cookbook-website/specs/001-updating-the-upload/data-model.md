# Data Model: Enhanced Nested Chain Analysis

## Entity Definitions

### NestedChain
Represents a device chain that exists within another chain, maintaining parent-child relationships and hierarchy information.

**Fields**:
- `id` (UUID, Primary Key): Unique identifier for the nested chain
- `rack_id` (UUID, Foreign Key): Reference to the parent rack
- `parent_chain_id` (UUID, Foreign Key, Nullable): Reference to parent chain (null for root-level chains)
- `chain_name` (String, 255): Human-readable name of the chain
- `chain_index` (Integer): Position index within parent container
- `depth_level` (Integer): Nesting depth (0 for root level, 1+ for nested)
- `device_count` (Integer): Number of devices directly in this chain
- `chain_type` (Enum): Type of chain (instrument, audio_effect, drum_pad, etc.)
- `is_empty` (Boolean): Whether chain contains any devices
- `xml_path` (Text): XPath location within original .adg file
- `analysis_metadata` (JSON): Additional chain-specific analysis data

**Relationships**:
- Belongs to Rack (many-to-one)
- Belongs to parent NestedChain (many-to-one, self-referential)
- Has many child NestedChains (one-to-many, self-referential)
- Has many ChainDevices (one-to-many)

**Validation Rules**:
- `chain_name` must be non-empty and max 255 characters
- `depth_level` must be >= 0 and <= 10 (reasonable depth limit)
- `device_count` must be >= 0
- `chain_index` must be >= 0
- `parent_chain_id` must exist if not null
- Circular references not allowed in parent-child relationships

### ChainDevice
Represents individual devices within nested chains, extending existing device detection.

**Fields**:
- `id` (UUID, Primary Key): Unique identifier for the device in chain
- `nested_chain_id` (UUID, Foreign Key): Reference to containing chain
- `device_name` (String, 255): Name of the device
- `device_type` (String, 100): Type/category of device
- `device_index` (Integer): Position within the chain
- `is_max_for_live` (Boolean): Whether device is Max for Live
- `device_parameters` (JSON): Device-specific parameters and settings
- `nested_racks` (JSON, Nullable): Information about nested racks within device

**Relationships**:
- Belongs to NestedChain (many-to-one)

**Validation Rules**:
- `device_name` must be non-empty and max 255 characters
- `device_type` must be from predefined list or 'Unknown'
- `device_index` must be >= 0
- `device_parameters` must be valid JSON

### EnhancedRackAnalysis
Extends existing rack analysis with comprehensive nested chain information.

**Fields**:
- `id` (UUID, Primary Key): Unique identifier for enhanced analysis
- `rack_id` (UUID, Foreign Key): Reference to the rack
- `analysis_version` (String, 20): Version of analysis engine used
- `has_nested_chains` (Boolean): Whether rack contains nested chains
- `max_nesting_depth` (Integer): Maximum depth of nesting found
- `total_chain_count` (Integer): Total number of chains (including nested)
- `nested_chain_tree` (JSON): Complete hierarchy representation
- `analysis_duration_ms` (Integer): Time taken for analysis
- `constitutional_compliant` (Boolean): Whether analysis meets constitutional requirements
- `reprocessing_required` (Boolean): Whether rack needs reanalysis
- `processed_at` (Timestamp): When enhanced analysis was completed

**Relationships**:
- Belongs to Rack (one-to-one)
- Has many NestedChains through rack_id

**Validation Rules**:
- `analysis_version` must match current engine version format
- `max_nesting_depth` must be >= 0 and <= 10
- `total_chain_count` must be >= 0
- `analysis_duration_ms` must be > 0
- `nested_chain_tree` must be valid JSON with hierarchical structure

## State Transitions

### Rack Analysis State Machine
1. **Uploaded**: Rack file uploaded but not analyzed
2. **Analyzing**: Basic analysis in progress
3. **Enhanced_Analyzing**: Nested chain analysis in progress
4. **Analysis_Complete**: All analysis complete and constitutional compliant
5. **Reprocessing_Required**: Existing analysis needs enhancement
6. **Analysis_Failed**: Analysis failed validation or constitutional requirements

### Chain Detection Process
1. **Parse_Root_Chains**: Identify top-level chains in rack
2. **Recursive_Parse**: Recursively analyze each chain for nested structures
3. **Validate_Hierarchy**: Ensure hierarchy consistency and depth limits
4. **Generate_Tree**: Create complete nested chain tree structure
5. **Constitutional_Check**: Verify all chains detected per constitutional requirement

## Database Schema Extensions

### Migration Requirements
- Add `nested_chains` table with proper indexes
- Add `chain_devices` table linked to nested chains
- Add `enhanced_rack_analyses` table
- Update existing `racks` table with `enhanced_analysis_complete` flag
- Create indexes on `parent_chain_id`, `depth_level`, and `rack_id` for query performance
- Add foreign key constraints with cascade delete for data integrity

### Performance Considerations
- Index on (`rack_id`, `depth_level`) for hierarchical queries
- Index on (`parent_chain_id`) for parent-child relationship queries
- Composite index on (`rack_id`, `chain_type`) for filtering
- JSON column indexes on frequently queried metadata fields

## Backward Compatibility
- Existing rack analysis data remains unchanged
- New analysis data stored in separate tables with foreign key relationships
- API responses maintain existing format with optional nested chain data
- Gradual migration strategy allows for rollback if necessary