#!/usr/bin/env python3
"""
CLI validation mode for button locations.

This script allows you to interactively validate button positions by:
1. Pressing Enter to enter validation mode
2. Selecting a button from a numbered list
3. Moving the mouse to that button's click location

Calibration mode:
1. Goes through each button sequentially
2. Moves mouse to current setpoint
3. User manually moves mouse to correct position
4. Press Enter to capture coordinates
5. Outputs corrected BUTTONS_CONFIG at the end
"""

import pyautogui
import copy
from .hardware import create_hardware_controller


def get_hardware_controller():
    """Get the hardware controller instance."""
    try:
        controller = create_hardware_controller()
        controller.initialize()
        return controller
    except Exception as e:
        print(f"ERROR: Failed to initialize hardware controller: {e}")
        print(f"Make sure HARDWARE_MODE or SEM_MODE environment variable is set correctly.")
        raise


def display_button_list(controller):
    """Display a numbered list of all available buttons."""
    button_config = controller.get_button_config()
    buttons = button_config.get("buttons", {})
    available_buttons = [(name, info) for name, info in buttons.items() if info is not None]
    
    print("\n" + "=" * 70)
    print(f"AVAILABLE BUTTONS ({controller.hardware_name}):")
    print("=" * 70)
    
    for idx, (name, info) in enumerate(available_buttons, start=1):
        center = info.get("center", {})
        notes = info.get("notes", "No description")
        print(f"  {idx:2d}. {name:30s} | Center: ({center.get('x', 'N/A')}, {center.get('y', 'N/A')})")
        print(f"      Notes: {notes}")
    
    print("=" * 70)
    print()
    
    return available_buttons


def move_to_button(button_name, button_info):
    """Move the mouse to the center of the specified button."""
    center = button_info.get("center")
    
    if not center:
        print(f"ERROR: Button '{button_name}' has no center coordinates!")
        return False
    
    x = center.get("x")
    y = center.get("y")
    
    if x is None or y is None:
        print(f"ERROR: Button '{button_name}' has invalid center coordinates!")
        return False
    
    print(f"\nMoving mouse to button '{button_name}' at ({x}, {y})...")
    pyautogui.moveTo(x, y, duration=0.3)
    print(f"Mouse moved! (Press Ctrl+C to exit validation mode)")
    return True


def validation_mode():
    """Main validation mode loop."""
    controller = get_hardware_controller()
    
    print("\n" + "=" * 70)
    print("BUTTON VALIDATION MODE")
    print("=" * 70)
    print(f"Hardware: {controller.hardware_name}")
    print("This mode will help you validate button positions.")
    print("You can select buttons by number and the mouse will move to their locations.")
    print()
    
    available_buttons = display_button_list(controller)
    
    while True:
        try:
            user_input = input("Enter button number (or 'q' to quit, 'r' to refresh list): ").strip().lower()
            
            if user_input == 'q':
                print("\nExiting validation mode...")
                break
            
            if user_input == 'r':
                print()
                available_buttons = display_button_list(controller)
                continue
            
            try:
                button_num = int(user_input)
            except ValueError:
                print("Invalid input! Please enter a number, 'q' to quit, or 'r' to refresh.\n")
                continue
            
            if button_num < 1 or button_num > len(available_buttons):
                print(f"Invalid button number! Please enter a number between 1 and {len(available_buttons)}.\n")
                continue
            
            button_name, button_info = available_buttons[button_num - 1]
            move_to_button(button_name, button_info)
            print()
            
        except KeyboardInterrupt:
            print("\n\nExiting validation mode...")
            break
        except Exception as e:
            print(f"\nERROR: An error occurred: {e}\n")


def update_bbox_from_center(button_info, new_center_x, new_center_y):
    """
    Update bbox coordinates based on new center, maintaining the same size.
    
    Args:
        button_info: Original button info dict
        new_center_x: New center x coordinate
        new_center_y: New center y coordinate
        
    Returns:
        dict: Updated bbox with new coordinates
    """
    old_center = button_info.get("center", {})
    old_bbox = button_info.get("bbox", {})
    
    old_center_x = old_center.get("x", new_center_x)
    old_center_y = old_center.get("y", new_center_y)
    
    # Calculate offset
    offset_x = new_center_x - old_center_x
    offset_y = new_center_y - old_center_y
    
    # Apply offset to bbox
    new_bbox = {
        "x1": old_bbox.get("x1", new_center_x) + offset_x,
        "y1": old_bbox.get("y1", new_center_y) + offset_y,
        "x2": old_bbox.get("x2", new_center_x) + offset_x,
        "y2": old_bbox.get("y2", new_center_y) + offset_y,
    }
    
    return new_bbox


def format_config_dict(config, hardware_mode):
    """
    Format the button config as a JSON string for easy copy-paste.
    
    Args:
        config: The button configuration dictionary (with buttons_by_state and common_buttons)
        hardware_mode: Hardware mode identifier
        
    Returns:
        str: Formatted JSON string
    """
    import json
    return json.dumps(config, indent=2, sort_keys=True)


def calibration_mode():
    """Calibration mode: guides through each button to capture corrected coordinates."""
    controller = get_hardware_controller()
    
    print("\n" + "=" * 70)
    print("BUTTON CALIBRATION MODE")
    print("=" * 70)
    print(f"Hardware: {controller.hardware_name}")
    print("This mode will guide you through calibrating each button.")
    print("For each button:")
    print("  1. Mouse will move to the current setpoint")
    print("  2. Manually move mouse to the exact correct position")
    print("  3. Press ENTER to capture the new coordinates")
    print("  4. Press 's' to skip a button")
    print("  5. Press 'q' to quit and see results")
    print()
    
    # Get button configuration from hardware controller
    # Access button_config attribute (available on all hardware controllers)
    button_config_obj = controller.button_config
    button_config = button_config_obj.get_config()
    
    # Create a deep copy to modify - use the internal structure
    calibrated_config = {
        "image_size": copy.deepcopy(button_config_obj.image_size),
        "buttons_by_state": copy.deepcopy(button_config_obj.buttons_by_state),
        "common_buttons": copy.deepcopy(button_config_obj.common_buttons),
    }
    
    # Get all buttons (from common_buttons and all states)
    all_buttons = {}
    all_buttons.update(calibrated_config["common_buttons"])
    for state_buttons in calibrated_config["buttons_by_state"].values():
        all_buttons.update(state_buttons)
    
    calibrated_buttons = []
    skipped_buttons = []
    
    print(f"Starting calibration for {len([b for b in all_buttons.items() if b[1] is not None])} buttons...")
    print("=" * 70)
    print()
    
    for button_name, button_info in all_buttons.items():
        if button_info is None:
            # Skip None buttons
            continue
        
        center = button_info.get("center")
        if not center:
            print(f"WARNING: Button '{button_name}' has no center coordinates. Skipping...")
            skipped_buttons.append(button_name)
            continue
        
        old_x = center.get("x")
        old_y = center.get("y")
        
        if old_x is None or old_y is None or (old_x == 0 and old_y == 0):
            print(f"WARNING: Button '{button_name}' has invalid center coordinates. Skipping...")
            skipped_buttons.append(button_name)
            continue
        
        notes = button_info.get("notes", "No description")
        
        print(f"\n{'=' * 70}")
        print(f"Button: {button_name}")
        print(f"Notes: {notes}")
        print(f"Current position: ({old_x}, {old_y})")
        print(f"{'=' * 70}")
        print("Moving mouse to current setpoint...")
        
        try:
            # Move mouse to current position
            pyautogui.moveTo(old_x, old_y, duration=0.5)
            print("Mouse moved! Now manually move it to the exact correct position.")
            print("Then press ENTER to capture, 's' to skip, or 'q' to quit.")
            
            user_input = ''
            while True:
                user_input = input("> ").strip().lower()
                
                if user_input == 'q':
                    print("\nQuitting calibration mode...")
                    break
                elif user_input == 's':
                    print(f"Skipping '{button_name}'...")
                    skipped_buttons.append(button_name)
                    break
                elif user_input == '':
                    # Enter pressed - capture current mouse position
                    current_x, current_y = pyautogui.position()
                    print(f"Captured position: ({current_x}, {current_y})")
                    
                    # Update bbox relative to new center
                    new_bbox = update_bbox_from_center(
                        button_info,
                        current_x,
                        current_y
                    )
                    
                    # Update the calibrated config - find which dict contains this button
                    updated_button = {
                        "bbox": new_bbox,
                        "center": {"x": current_x, "y": current_y},
                        "notes": notes,
                    }
                    if button_info.get("requires_confirmation"):
                        updated_button["requires_confirmation"] = True
                    
                    # Update in common_buttons or buttons_by_state
                    if button_name in calibrated_config["common_buttons"]:
                        calibrated_config["common_buttons"][button_name] = updated_button
                    else:
                        # Find which state contains this button
                        for state_name, state_buttons in calibrated_config["buttons_by_state"].items():
                            if button_name in state_buttons:
                                calibrated_config["buttons_by_state"][state_name][button_name] = updated_button
                                break
                    
                    calibrated_buttons.append(button_name)
                    print(f"✓ Calibrated '{button_name}'")
                    break
                else:
                    print("Invalid input. Press ENTER to capture, 's' to skip, or 'q' to quit.")
            
            if user_input == 'q':
                break
                
        except KeyboardInterrupt:
            print("\n\nCalibration interrupted.")
            break
        except Exception as e:
            print(f"\nERROR processing '{button_name}': {e}")
            skipped_buttons.append(button_name)
            continue
    
    # Display results
    print("\n" + "=" * 70)
    print("CALIBRATION COMPLETE")
    print("=" * 70)
    print(f"Calibrated: {len(calibrated_buttons)} buttons")
    if skipped_buttons:
        print(f"Skipped: {len(skipped_buttons)} buttons: {', '.join(skipped_buttons)}")
    print()
    print("=" * 70)
    print("CORRECTED BUTTON CONFIGURATION (JSON format):")
    print("=" * 70)
    print()
    print(format_config_dict(calibrated_config, controller.hardware_mode))
    print()
    print("=" * 70)
    print()

    # Persist calibration using hardware-specific save function
    try:
        # Import the save function based on hardware mode
        hardware_mode = controller.hardware_mode
        if hardware_mode == "tescan_sem":
            from .hardware.tescan_sem.buttons import save_buttons_config_override
        elif hardware_mode == "edax_eds":
            from .hardware.edax_eds.buttons import save_buttons_config_override
        elif hardware_mode == "kw_dds":
            from .hardware.kw_dds.buttons import save_buttons_config_override
        else:
            raise ValueError(f"Unknown hardware mode: {hardware_mode}")
        
        saved_path = save_buttons_config_override(calibrated_config)
        print(f"Saved calibrated button configuration to: {saved_path}")
        print("This file will be loaded automatically on next start.")
    except Exception as e:
        print(f"WARNING: Failed to save calibrated button configuration: {e}")
        print("Please save the JSON output above manually.")


def main():
    """Main entry point for the validation script."""
    print("\n" + "=" * 70)
    print("SEM BUTTON VALIDATION TOOL")
    print("=" * 70)
    print("\nSelect mode:")
    print("  1. Validation mode (select buttons individually)")
    print("  2. Calibration mode (guided calibration of all buttons)")
    print()
    
    try:
        mode = input("Enter mode (1 or 2, or press ENTER for validation): ").strip()
        
        if mode == '2':
            calibration_mode()
        else:
            validation_mode()
    except KeyboardInterrupt:
        print("\n\nExiting...")
    except Exception as e:
        print(f"\nERROR: {e}")


if __name__ == "__main__":
    main()

