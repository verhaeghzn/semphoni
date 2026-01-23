# Architecture Overview

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Cloud Server                            │
│                    (Reverb/Pusher WebSocket)                    │
└────────────────────────────┬────────────────────────────────────┘
                              │
                              │ WebSocket Connection
                              │
┌─────────────────────────────▼────────────────────────────────────┐
│                    semphoni-sem-client                           │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Reverb Client (reverb_client.py)             │  │
│  │  - WebSocket connection management                        │  │
│  │  - Message routing                                        │  │
│  │  - Heartbeat/presence                                    │  │
│  └───────────────────┬──────────────────────────────────────┘  │
│                      │                                           │
│                      ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │          Command Executor (core/command_executor.py)      │  │
│  │  - Routes commands to appropriate handlers                │  │
│  │  - Handles common commands (screenshot)                   │  │
│  │  - Delegates hardware-specific commands                   │  │
│  └───────────────────┬──────────────────────────────────────┘  │
│                      │                                           │
│                      ▼                                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │      Hardware Controller (HardwareController ABC)        │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │  │
│  │  │ TESCAN SEM   │  │  EDAX EDS    │  │   KW-DDS     │  │  │
│  │  │ Controller   │  │  Controller  │  │  Controller  │  │  │
│  │  └──────────────┘  └──────────────┘  └──────────────┘  │  │
│  │                                                           │  │
│  │  Each controller provides:                                │  │
│  │  - get_metrics()                                          │  │
│  │  - get_button_config()                                    │  │
│  │  - validate_button()                                      │  │
│  │  - execute_command()                                      │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │        Screenshot Manager (core/screenshot_manager.py)    │  │
│  │  - Monitor capture                                        │  │
│  │  - Image encoding                                         │  │
│  │  - Upload to server                                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │         GUI Automation (utils/gui_automation.py)         │  │
│  │  - Button clicking                                       │  │
│  │  - Mouse movement                                        │  │
│  │  - Keyboard input                                        │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                              │
                              │ SDK / GUI
                              │
┌─────────────────────────────▼────────────────────────────────────┐
│                      Hardware Layer                               │
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ TESCAN SEM   │  │  EDAX EDS    │  │   KW-DDS     │          │
│  │              │  │              │  │              │          │
│  │ SharkSEM SDK │  │  GUI Only    │  │  GUI Only    │          │
│  │ (TCP)        │  │              │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

### Core Infrastructure

#### Reverb Client
- **File**: `reverb_client.py`
- **Responsibility**: WebSocket connection to cloud server, message handling, presence management
- **Changes**: Minimal - delegates command execution to CommandExecutor

#### Command Executor
- **File**: `core/command_executor.py` (NEW)
- **Responsibility**: 
  - Routes commands to appropriate handlers
  - Handles common commands (screenshot)
  - Delegates hardware-specific commands to HardwareController

#### Screenshot Manager
- **File**: `core/screenshot_manager.py` (NEW)
- **Responsibility**: 
  - Monitor capture using mss
  - JPEG encoding
  - Upload to server via HTTP POST

### Hardware Abstraction

#### HardwareController (Abstract Base Class)
- **File**: `core/hardware_controller.py` (NEW)
- **Responsibility**: Defines interface all hardware implementations must follow
- **Methods**:
  - `get_metrics()` - Hardware telemetry
  - `get_button_config()` - Button configuration
  - `validate_button()` - Button validation
  - `execute_command()` - Hardware-specific commands

#### BaseHardwareController
- **File**: `hardware/base.py` (NEW)
- **Responsibility**: Common implementation shared by all hardware modes
- **Provides**: Default implementations for common operations

### Hardware Implementations

#### TESCAN SEM
- **Directory**: `hardware/tescan_sem/`
- **Components**:
  - `controller.py` - Main controller implementation
  - `metrics.py` - TESCAN metrics reader (moved from `sem_metrics.py`)
  - `sdk_client.py` - SharkSEM client (moved from `tescan_sharksem.py`)
  - `buttons.py` - Button configuration (moved from `config.py`)

#### EDAX EDS (Future)
- **Directory**: `hardware/edax_eds/`
- **Components**:
  - `controller.py` - Main controller (GUI automation only)
  - `buttons.py` - Button configuration for EDAX software

#### KW-DDS (Future)
- **Directory**: `hardware/kw_dds/`
- **Components**:
  - `controller.py` - Main controller (GUI automation only)
  - `buttons.py` - Button configuration for KW-DDS software

## Data Flow

### Command Execution Flow

```
1. Cloud Server sends command via WebSocket
   ↓
2. Reverb Client receives message
   ↓
3. Command Executor routes command
   ↓
4a. Common command (screenshot) → Screenshot Manager
4b. Hardware command → Hardware Controller
   ↓
5. Hardware Controller executes command
   ↓
6. Response sent back via WebSocket
```

### Metrics Retrieval Flow

```
1. Cloud Server requests metrics
   ↓
2. Command Executor routes to Hardware Controller
   ↓
3. Hardware Controller calls get_metrics()
   ↓
4a. TESCAN: Uses SDK client to query SharkSEM
4b. EDAX/KW-DDS: Returns "not supported" (GUI only)
   ↓
5. Metrics returned to cloud server
```

### Button Click Flow

```
1. Cloud Server sends clickButton command
   ↓
2. Command Executor routes to Hardware Controller
   ↓
3. Hardware Controller validates button
   ↓
4. GUI Automation performs click
   ↓
5. Confirmation sent to cloud server
```

## Configuration

### Environment Variables

#### Common (All Hardware Modes)
- `HARDWARE_MODE` - Hardware mode selection (`tescan_sem`, `edax_eds`, `kw_dds`)
- `REVERB_WS_HOST` - WebSocket host
- `REVERB_APP_KEY` - App key
- `REVERB_AUTH_URL` - Authentication URL
- `REVERB_CLIENT_KEY` - Client authentication key
- `SEMPC_MONITOR_NUMBER` - Monitor for screenshots
- `SCREENSHOT_JPEG_QUALITY` - JPEG quality (1-100)

#### TESCAN SEM Specific
- `TESCAN_SDK_HOST` - SharkSEM host (default: 127.0.0.1)
- `TESCAN_SDK_PORT` - SharkSEM port (default: 8300)
- `TESCAN_SDK_TIMEOUT_S` - SDK timeout (default: 2.0)

#### EDAX EDS Specific
- (To be determined during implementation)

#### KW-DDS Specific
- (To be determined during implementation)

## Extension Points

### Adding a New Hardware Mode

1. Create directory: `hardware/new_hardware/`
2. Implement `NewHardwareController` extending `BaseHardwareController`
3. Implement required methods:
   - `get_metrics()` - If SDK available, otherwise return "not supported"
   - `get_button_config()` - Return button configuration
   - `validate_button()` - Validate button names
   - `execute_command()` - Handle hardware-specific commands
4. Register in `hardware/__init__.py` factory function
5. Update documentation

### Adding a New Command

1. Add command handling to `HardwareController.execute_command()` in relevant hardware implementation
2. If command is common to all hardware, add to `CommandExecutor`
3. Update documentation
4. Update cloud server to send new command

## Testing Strategy

### Unit Tests
- Test each hardware controller independently
- Mock SDK connections for TESCAN
- Test button validation logic
- Test command execution

### Integration Tests
- Test Reverb client connection
- Test command routing
- Test screenshot capture and upload
- Test metrics retrieval

### Hardware-Specific Tests
- TESCAN: Test SDK connectivity and metrics
- EDAX/KW-DDS: Test GUI automation (when implemented)
