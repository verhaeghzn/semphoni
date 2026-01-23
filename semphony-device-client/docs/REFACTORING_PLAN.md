# Refactoring Plan: Multi-Hardware Support Architecture

## Overview

This document outlines the refactoring plan to transform the `semphony-device-client` from a TESCAN SEM-specific client into a multi-hardware client supporting:

1. **TESCAN SEM** (current implementation)
2. **EDAX EDS** (future - GUI automation only)
3. **KW-DDS** (Kammrath Weiss Deformation Devices) (future - GUI automation only)
4. Additional hardware modes in the future

## Design Principles

1. **Single Mode Operation**: The client supports only ONE hardware mode at runtime (determined by environment variable)
2. **Plugin Architecture**: Each hardware mode is a self-contained plugin implementing a common interface
3. **Separation of Concerns**: 
   - Common infrastructure (Reverb connection, screenshots, command routing)
   - Hardware-specific implementations (metrics, button mappings, GUI automation)
4. **Academic Quality**: Well-documented, maintainable, extensible codebase suitable for academic publication
5. **Backward Compatibility**: Existing TESCAN SEM functionality must continue to work

## Architecture Design

### Directory Structure

```
device_client/
├── __init__.py
├── __main__.py
├── main.py                    # Entry point (unchanged)
├── config.py                  # General configuration (refactored)
├── reverb_client.py           # Reverb WebSocket client (refactored)
├── rest_main.py               # REST API server (refactored)
│
├── core/                      # NEW: Core infrastructure
│   ├── __init__.py
│   ├── hardware_controller.py # Abstract base class for hardware controllers
│   ├── command_executor.py   # Command routing and execution
│   └── screenshot_manager.py  # Screenshot capture and upload
│
├── hardware/                  # NEW: Hardware-specific implementations
│   ├── __init__.py
│   ├── base.py                # Base hardware controller implementation
│   ├── tescan_sem/            # TESCAN SEM implementation
│   │   ├── __init__.py
│   │   ├── controller.py      # TESCAN-specific controller
│   │   ├── metrics.py         # TESCAN metrics reader (moved from sem_metrics.py)
│   │   ├── sdk_client.py      # SharkSEM client (moved from tescan_sharksem.py)
│   │   └── buttons.py         # TESCAN button configuration (moved from config.py)
│   ├── edax_eds/              # EDAX EDS implementation (future)
│   │   ├── __init__.py
│   │   ├── controller.py
│   │   └── buttons.py
│   └── kw_dds/                # KW-DDS implementation (future)
│       ├── __init__.py
│       ├── controller.py
│       └── buttons.py
│
├── utils/                     # NEW: Shared utilities
│   ├── __init__.py
│   ├── button_utils.py        # Button validation (moved from utils.py)
│   └── gui_automation.py      # GUI automation helpers
│
└── routes/                    # REST API routes (refactored to use hardware abstraction)
    ├── __init__.py
    ├── button_routes.py
    ├── control_routes.py
    └── screenshot_routes.py
```

### Core Components

#### 1. HardwareController Interface (`core/hardware_controller.py`)

Abstract base class defining the contract all hardware implementations must follow:

```python
from abc import ABC, abstractmethod
from typing import Dict, Any, Optional

class HardwareController(ABC):
    """Abstract base class for hardware-specific controllers."""
    
    @property
    @abstractmethod
    def hardware_mode(self) -> str:
        """Return the hardware mode identifier (e.g., 'tescan_sem', 'edax_eds')."""
        pass
    
    @property
    @abstractmethod
    def hardware_name(self) -> str:
        """Return human-readable hardware name."""
        pass
    
    @abstractmethod
    def initialize(self) -> None:
        """Initialize the hardware connection (SDK, GUI detection, etc.)."""
        pass
    
    @abstractmethod
    def get_metrics(self) -> Dict[str, Any]:
        """
        Retrieve hardware telemetry/metrics.
        
        Returns:
            Dict with 'supported' key (bool) and hardware-specific metrics.
            If not supported, returns {'supported': False, 'message': '...'}.
        """
        pass
    
    @abstractmethod
    def get_button_config(self) -> Dict[str, Any]:
        """
        Return button configuration for this hardware.
        
        Returns:
            Dict with 'image_size' and 'buttons' keys matching current format.
        """
        pass
    
    @abstractmethod
    def validate_button(self, button_name: str) -> tuple[Dict[str, Any], Dict[str, int]]:
        """
        Validate and return button info and coordinates.
        
        Args:
            button_name: Name of the button to validate
            
        Returns:
            Tuple of (button_info dict, center dict with x, y coordinates)
            
        Raises:
            ButtonValidationError: If button is invalid
        """
        pass
    
    @abstractmethod
    def execute_command(self, command_name: str, payload: Dict[str, Any]) -> tuple[bool, str, Optional[Dict[str, Any]]]:
        """
        Execute a hardware-specific command.
        
        Args:
            command_name: Command identifier
            payload: Command parameters
            
        Returns:
            Tuple of (success: bool, message: str, result: Optional[Dict])
        """
        pass
    
    def get_screenshot_config(self) -> Dict[str, Any]:
        """
        Return screenshot configuration (monitor number, etc.).
        Can be overridden by hardware implementations.
        """
        return {
            "monitor_number": int(os.getenv("SEMPC_MONITOR_NUMBER", "2")),
            "jpeg_quality": int(os.getenv("SCREENSHOT_JPEG_QUALITY", "75")),
        }
```

#### 2. Hardware Factory (`hardware/__init__.py`)

Factory function to instantiate the correct hardware controller based on environment:

```python
def create_hardware_controller() -> HardwareController:
    """Factory function to create the appropriate hardware controller."""
    mode = os.getenv("HARDWARE_MODE", "tescan_sem").strip().lower()
    
    if mode == "tescan_sem":
        from .tescan_sem.controller import TescanSemController
        return TescanSemController()
    elif mode == "edax_eds":
        from .edax_eds.controller import EdaxEdsController
        return EdaxEdsController()
    elif mode == "kw_dds":
        from .kw_dds.controller import KwDdsController
        return KwDdsController()
    else:
        raise ValueError(f"Unknown hardware mode: {mode}")
```

#### 3. Command Executor (`core/command_executor.py`)

Centralized command routing that delegates to hardware controller:

```python
class CommandExecutor:
    """Executes commands by delegating to hardware controller."""
    
    def __init__(self, hardware_controller: HardwareController):
        self.hardware = hardware_controller
    
    def execute(self, command_name: str, payload: Dict[str, Any]) -> tuple[bool, str, Optional[Dict[str, Any]]]:
        """Execute a command, routing to appropriate handler."""
        # Common commands (screenshot, etc.)
        if command_name in ("screenshot", "getScreenshot", "get_screenshot"):
            return self._handle_screenshot(payload)
        
        # Hardware-specific commands
        return self.hardware.execute_command(command_name, payload)
    
    def _handle_screenshot(self, payload: Dict[str, Any]) -> tuple[bool, str, Optional[Dict[str, Any]]]:
        """Handle screenshot command using screenshot manager."""
        # Implementation moved from reverb_client.py
        ...
```

#### 4. Screenshot Manager (`core/screenshot_manager.py`)

Extracted screenshot functionality:

```python
class ScreenshotManager:
    """Manages screenshot capture and upload."""
    
    def __init__(self, hardware_controller: HardwareController):
        self.hardware = hardware_controller
    
    def capture_and_upload(self, payload: Dict[str, Any], upload_url: str, client_key: str) -> Dict[str, Any]:
        """Capture screenshot and upload to server."""
        # Implementation extracted from reverb_client.py
        ...
```

### Hardware Implementation Pattern

Each hardware mode follows this pattern:

#### Example: TESCAN SEM (`hardware/tescan_sem/controller.py`)

```python
from ..base import BaseHardwareController
from .metrics import TescanMetricsReader
from .buttons import TescanButtonConfig

class TescanSemController(BaseHardwareController):
    """TESCAN SEM hardware controller."""
    
    @property
    def hardware_mode(self) -> str:
        return "tescan_sem"
    
    @property
    def hardware_name(self) -> str:
        return "TESCAN SEM"
    
    def __init__(self):
        super().__init__()
        self.metrics_reader = TescanMetricsReader()
        self.button_config = TescanButtonConfig()
    
    def initialize(self) -> None:
        """Check TESCAN SDK connectivity."""
        self.metrics_reader.check_connectivity()
    
    def get_metrics(self) -> Dict[str, Any]:
        """Get TESCAN SEM metrics via SDK."""
        return self.metrics_reader.get_metrics()
    
    def get_button_config(self) -> Dict[str, Any]:
        """Return TESCAN button configuration."""
        return self.button_config.get_config()
    
    def validate_button(self, button_name: str) -> tuple[Dict[str, Any], Dict[str, int]]:
        """Validate TESCAN button."""
        return self.button_config.validate_button(button_name)
    
    def execute_command(self, command_name: str, payload: Dict[str, Any]) -> tuple[bool, str, Optional[Dict[str, Any]]]:
        """Execute TESCAN-specific commands."""
        # Handle button commands, etc.
        ...
```

### Configuration Changes

#### Environment Variables

- **`HARDWARE_MODE`** (new): Replaces `SEM_MODE`. Options:
  - `tescan_sem` (default for backward compatibility)
  - `edax_eds`
  - `kw_dds`
  
- **`SEM_MODE`** (deprecated): Still supported for backward compatibility, maps to `HARDWARE_MODE`

- Hardware-specific variables remain (e.g., `TESCAN_SDK_HOST`, etc.)

### Migration Strategy

1. **Phase 1: Create Infrastructure**
   - Create `core/` directory with abstract interfaces
   - Create `hardware/` directory structure
   - Implement base classes

2. **Phase 2: Refactor TESCAN**
   - Move TESCAN-specific code to `hardware/tescan_sem/`
   - Implement `TescanSemController`
   - Update imports throughout codebase
   - Test backward compatibility

3. **Phase 3: Create Stubs for New Hardware**
   - Create `hardware/edax_eds/` with stub implementation
   - Create `hardware/kw_dds/` with stub implementation
   - Document required implementation steps

4. **Phase 4: Documentation**
   - Update README with multi-hardware support
   - Create hardware-specific documentation
   - Add developer guide for adding new hardware

### Benefits

1. **Clear Separation**: Hardware-specific code isolated from infrastructure
2. **Easy Extension**: Adding new hardware = implementing one class
3. **Testability**: Each hardware mode can be tested independently
4. **Maintainability**: Changes to one hardware mode don't affect others
5. **Documentation**: Clear structure makes codebase self-documenting
6. **Academic Quality**: Professional architecture suitable for publication

### Backward Compatibility

- Existing environment variables continue to work
- Existing command names unchanged
- Existing button names unchanged
- Default behavior unchanged (TESCAN SEM if no mode specified)

## Implementation Checklist

- [ ] Create directory structure
- [ ] Implement `HardwareController` abstract base class
- [ ] Implement `BaseHardwareController` with common functionality
- [ ] Create hardware factory
- [ ] Move TESCAN code to `hardware/tescan_sem/`
- [ ] Implement `TescanSemController`
- [ ] Refactor `reverb_client.py` to use hardware abstraction
- [ ] Refactor `rest_main.py` to use hardware abstraction
- [ ] Create stub implementations for EDAX EDS and KW-DDS
- [ ] Update configuration handling
- [ ] Update documentation
- [ ] Test backward compatibility
- [ ] Update README
