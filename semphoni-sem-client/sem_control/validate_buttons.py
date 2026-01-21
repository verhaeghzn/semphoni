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
from .config import BUTTONS, BUTTONS_CONFIG, IMAGE_SIZE
from .config import save_buttons_config_override, get_buttons_config_override_path


def display_button_list():
    """Display a numbered list of all available buttons."""
    available_buttons = [(name, info) for name, info in BUTTONS.items() if info is not None]
    
    print("\n" + "=" * 70)
    print("AVAILABLE BUTTONS:")
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
    print("\n" + "=" * 70)
    print("BUTTON VALIDATION MODE")
    print("=" * 70)
    print("This mode will help you validate button positions.")
    print("You can select buttons by number and the mouse will move to their locations.")
    print()
    
    available_buttons = display_button_list()
    
    while True:
        try:
            user_input = input("Enter button number (or 'q' to quit, 'r' to refresh list): ").strip().lower()
            
            if user_input == 'q':
                print("\nExiting validation mode...")
                break
            
            if user_input == 'r':
                print()
                available_buttons = display_button_list()
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


def format_config_dict(config):
    """
    Format the BUTTONS_CONFIG as a Python dict string for easy copy-paste.
    
    Args:
        config: The BUTTONS_CONFIG dictionary
        
    Returns:
        str: Formatted Python dict string
    """
    lines = ['BUTTONS_CONFIG = {']
    lines.append(f'    "image_size": {{"width": {config["image_size"]["width"]}, "height": {config["image_size"]["height"]}}},')
    lines.append('    "buttons": {')
    
    button_items = list(config["buttons"].items())
    for idx, (name, info) in enumerate(button_items):
        if info is None:
            lines.append(f'        "{name}": None,')
        else:
            bbox = info.get("bbox", {})
            center = info.get("center", {})
            notes = info.get("notes", "")
            requires_confirmation = info.get("requires_confirmation", False)
            
            lines.append(f'        "{name}": {{')
            lines.append(f'            "bbox": {{"x1": {bbox.get("x1")}, "y1": {bbox.get("y1")}, "x2": {bbox.get("x2")}, "y2": {bbox.get("y2")}}},')
            lines.append(f'            "center": {{"x": {center.get("x")}, "y": {center.get("y")}}},')
            lines.append(f'            "notes": "{notes}",')
            if requires_confirmation:
                lines.append(f'            "requires_confirmation": {requires_confirmation},')
            lines.append('        },')
    
    lines.append('    },')
    lines.append('}')
    
    return '\n'.join(lines)


def calibration_mode():
    """Calibration mode: guides through each button to capture corrected coordinates."""
    print("\n" + "=" * 70)
    print("BUTTON CALIBRATION MODE")
    print("=" * 70)
    print("This mode will guide you through calibrating each button.")
    print("For each button:")
    print("  1. Mouse will move to the current setpoint")
    print("  2. Manually move mouse to the exact correct position")
    print("  3. Press ENTER to capture the new coordinates")
    print("  4. Press 's' to skip a button")
    print("  5. Press 'q' to quit and see results")
    print()
    
    # Create a deep copy of the config to modify
    calibrated_config = copy.deepcopy(BUTTONS_CONFIG)
    
    # Get all buttons (including None ones to preserve order)
    all_buttons = list(BUTTONS.items())
    calibrated_buttons = []
    skipped_buttons = []
    
    print(f"Starting calibration for {len([b for b in all_buttons if b[1] is not None])} buttons...")
    print("=" * 70)
    print()
    
    for button_name, button_info in all_buttons:
        if button_info is None:
            # Skip None buttons but keep them in the config
            continue
        
        center = button_info.get("center")
        if not center:
            print(f"WARNING: Button '{button_name}' has no center coordinates. Skipping...")
            skipped_buttons.append(button_name)
            continue
        
        old_x = center.get("x")
        old_y = center.get("y")
        
        if old_x is None or old_y is None:
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
                    
                    # Update the calibrated config
                    calibrated_config["buttons"][button_name]["center"] = {
                        "x": current_x,
                        "y": current_y
                    }
                    
                    # Update bbox relative to new center
                    new_bbox = update_bbox_from_center(
                        button_info,
                        current_x,
                        current_y
                    )
                    calibrated_config["buttons"][button_name]["bbox"] = new_bbox
                    
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
    print("CORRECTED BUTTONS_CONFIG (copy-paste this into Server/config.py):")
    print("=" * 70)
    print()
    print(format_config_dict(calibrated_config))
    print()
    print("=" * 70)
    print()

    # Persist calibration so it survives code updates.
    try:
        saved_path = save_buttons_config_override(calibrated_config)
        print(f"Saved calibrated BUTTONS_CONFIG to: {saved_path}")
        print("This file will be loaded automatically on server start.")
    except Exception as e:
        print(f"WARNING: Failed to save calibrated BUTTONS_CONFIG to {get_buttons_config_override_path()}: {e}")


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

