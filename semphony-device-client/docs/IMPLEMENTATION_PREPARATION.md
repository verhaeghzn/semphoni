# Implementation Preparation: EDAX EDS and KW-DDS

## Overview

This document outlines the preparation steps and requirements for implementing support for EDAX EDS and KW-DDS hardware modes. Both will use GUI automation only (no SDKs available).

## EDAX EDS Implementation

### Hardware Information
- **Type**: Energy Dispersive X-ray Spectroscopy (EDS) system
- **Control Method**: GUI automation only (no SDK available)
- **Software**: EDAX EDS control software (exact name/version TBD)

### Required Information Gathering

#### 1. Software Identification
- [ ] Identify exact software name and version
- [ ] Document software installation path
- [ ] Document window title/class names for identification
- [ ] Document screen resolution requirements

#### 2. Button Mapping
- [ ] Identify all control buttons/controls needed for remote operation
- [ ] Document button locations (screenshots with coordinates)
- [ ] Document button states (enabled/disabled conditions)
- [ ] Document any confirmation dialogs
- [ ] Create button configuration JSON structure

#### 3. Metrics/Telemetry
- [ ] Determine if any metrics can be read from GUI (text fields, status indicators)
- [ ] Document metric locations and extraction methods
- [ ] If no metrics available, document "not supported" response

#### 4. GUI Automation Requirements
- [ ] Document any special GUI automation needs (window focus, tab navigation, etc.)
- [ ] Document any timing requirements (delays between actions)
- [ ] Document any confirmation dialogs or warnings

#### 5. Screenshot Configuration
- [ ] Determine which monitor displays EDAX software
- [ ] Document optimal screenshot settings
- [ ] Test screenshot capture and quality

### Implementation Checklist

- [ ] Create `hardware/edax_eds/` directory structure
- [ ] Implement `EdaxEdsController` class
- [ ] Create button configuration file (`buttons.py`)
- [ ] Implement `get_metrics()` (return "not supported" initially)
- [ ] Implement `validate_button()` using button configuration
- [ ] Implement `execute_command()` for button clicks
- [ ] Add GUI automation helpers if needed
- [ ] Create calibration/validation tools
- [ ] Write unit tests
- [ ] Document in README

### Button Configuration Template

```python
# hardware/edax_eds/buttons.py

DEFAULT_BUTTONS_CONFIG = {
    "image_size": {"width": 1920, "height": 1080},  # Adjust based on actual screen
    "buttons": {
        "button_name_1": {
            "bbox": {"x1": 100, "y1": 200, "x2": 200, "y2": 250},
            "center": {"x": 150, "y": 225},
            "notes": "Description of button",
            "requires_confirmation": False,  # Set to True if confirmation dialog appears
        },
        # ... more buttons
    },
}
```

### Example Controller Structure

```python
# hardware/edax_eds/controller.py

from ..base import BaseHardwareController
from .buttons import EdaxButtonConfig

class EdaxEdsController(BaseHardwareController):
    """EDAX EDS hardware controller (GUI automation only)."""
    
    @property
    def hardware_mode(self) -> str:
        return "edax_eds"
    
    @property
    def hardware_name(self) -> str:
        return "EDAX EDS"
    
    def __init__(self):
        super().__init__()
        self.button_config = EdaxButtonConfig()
    
    def initialize(self) -> None:
        """Check if EDAX software is running."""
        # TODO: Implement window detection
        pass
    
    def get_metrics(self) -> Dict[str, Any]:
        """EDAX EDS metrics not available via SDK."""
        return {
            "hardware_mode": "edax_eds",
            "supported": False,
            "message": "EDAX EDS metrics not available (GUI automation only).",
        }
    
    def get_button_config(self) -> Dict[str, Any]:
        """Return EDAX button configuration."""
        return self.button_config.get_config()
    
    def validate_button(self, button_name: str) -> tuple[Dict[str, Any], Dict[str, int]]:
        """Validate EDAX button."""
        return self.button_config.validate_button(button_name)
    
    def execute_command(self, command_name: str, payload: Dict[str, Any]) -> tuple[bool, str, Optional[Dict[str, Any]]]:
        """Execute EDAX-specific commands."""
        if command_name in ("gotoButton", "goto_button"):
            # Implement button navigation
            ...
        elif command_name in ("clickButton", "click_button"):
            # Implement button clicking
            ...
        else:
            return False, f"Unknown command: {command_name}", None
```

## KW-DDS Implementation

### Hardware Information
- **Type**: Kammrath Weiss Deformation Devices (DDS)
- **Control Method**: GUI automation only (no SDK available)
- **Software**: KW-DDS control software (exact name/version TBD)

### Required Information Gathering

#### 1. Software Identification
- [ ] Identify exact software name and version
- [ ] Document software installation path
- [ ] Document window title/class names for identification
- [ ] Document screen resolution requirements

#### 2. Button Mapping
- [ ] Identify all control buttons/controls needed for remote operation
- [ ] Document button locations (screenshots with coordinates)
- [ ] Document button states (enabled/disabled conditions)
- [ ] Document any confirmation dialogs
- [ ] Create button configuration JSON structure

#### 3. Metrics/Telemetry
- [ ] Determine if any metrics can be read from GUI (text fields, status indicators)
- [ ] Document metric locations and extraction methods
- [ ] If no metrics available, document "not supported" response

#### 4. GUI Automation Requirements
- [ ] Document any special GUI automation needs (window focus, tab navigation, etc.)
- [ ] Document any timing requirements (delays between actions)
- [ ] Document any confirmation dialogs or warnings

#### 5. Screenshot Configuration
- [ ] Determine which monitor displays KW-DDS software
- [ ] Document optimal screenshot settings
- [ ] Test screenshot capture and quality

### Implementation Checklist

- [ ] Create `hardware/kw_dds/` directory structure
- [ ] Implement `KwDdsController` class
- [ ] Create button configuration file (`buttons.py`)
- [ ] Implement `get_metrics()` (return "not supported" initially)
- [ ] Implement `validate_button()` using button configuration
- [ ] Implement `execute_command()` for button clicks
- [ ] Add GUI automation helpers if needed
- [ ] Create calibration/validation tools
- [ ] Write unit tests
- [ ] Document in README

### Button Configuration Template

```python
# hardware/kw_dds/buttons.py

DEFAULT_BUTTONS_CONFIG = {
    "image_size": {"width": 1920, "height": 1080},  # Adjust based on actual screen
    "buttons": {
        "button_name_1": {
            "bbox": {"x1": 100, "y1": 200, "x2": 200, "y2": 250},
            "center": {"x": 150, "y": 225},
            "notes": "Description of button",
            "requires_confirmation": False,
        },
        # ... more buttons
    },
}
```

### Example Controller Structure

```python
# hardware/kw_dds/controller.py

from ..base import BaseHardwareController
from .buttons import KwDdsButtonConfig

class KwDdsController(BaseHardwareController):
    """KW-DDS hardware controller (GUI automation only)."""
    
    @property
    def hardware_mode(self) -> str:
        return "kw_dds"
    
    @property
    def hardware_name(self) -> str:
        return "Kammrath Weiss DDS"
    
    def __init__(self):
        super().__init__()
        self.button_config = KwDdsButtonConfig()
    
    def initialize(self) -> None:
        """Check if KW-DDS software is running."""
        # TODO: Implement window detection
        pass
    
    def get_metrics(self) -> Dict[str, Any]:
        """KW-DDS metrics not available via SDK."""
        return {
            "hardware_mode": "kw_dds",
            "supported": False,
            "message": "KW-DDS metrics not available (GUI automation only).",
        }
    
    def get_button_config(self) -> Dict[str, Any]:
        """Return KW-DDS button configuration."""
        return self.button_config.get_config()
    
    def validate_button(self, button_name: str) -> tuple[Dict[str, Any], Dict[str, int]]:
        """Validate KW-DDS button."""
        return self.button_config.validate_button(button_name)
    
    def execute_command(self, command_name: str, payload: Dict[str, Any]) -> tuple[bool, str, Optional[Dict[str, Any]]]:
        """Execute KW-DDS-specific commands."""
        if command_name in ("gotoButton", "goto_button"):
            # Implement button navigation
            ...
        elif command_name in ("clickButton", "click_button"):
            # Implement button clicking
            ...
        else:
            return False, f"Unknown command: {command_name}", None
```

## Common Implementation Steps

### 1. Create Directory Structure

```bash
mkdir -p hardware/edax_eds
mkdir -p hardware/kw_dds
touch hardware/edax_eds/__init__.py
touch hardware/edax_eds/controller.py
touch hardware/edax_eds/buttons.py
touch hardware/kw_dds/__init__.py
touch hardware/kw_dds/controller.py
touch hardware/kw_dds/buttons.py
```

### 2. Implement Stub Controllers

Start with minimal implementations that:
- Return "not supported" for metrics
- Have empty button configurations
- Handle basic button commands
- Can be extended as requirements are gathered

### 3. Testing Strategy

- **Unit Tests**: Test controller initialization, button validation
- **Integration Tests**: Test command execution via Reverb client
- **Manual Testing**: Use calibration mode to map buttons
- **Hardware Testing**: Test with actual hardware when available

### 4. Documentation Requirements

- Hardware-specific README section
- Button mapping documentation
- Calibration instructions
- Troubleshooting guide

## Next Steps

1. **Gather Requirements**: Work with hardware operators to identify:
   - Exact software versions
   - Required control operations
   - Button locations
   - Any special requirements

2. **Create Stubs**: Implement minimal controllers that can be extended

3. **Calibration Tools**: Use existing calibration tools to map buttons

4. **Iterative Development**: Extend functionality as requirements become clear

5. **Testing**: Test with actual hardware when available

## Notes

- Both EDAX EDS and KW-DDS will follow the same implementation pattern
- GUI automation will use the same utilities as TESCAN SEM
- Button configuration format is consistent across all hardware modes
- Metrics will return "not supported" initially (can be extended if GUI text extraction is possible)
