import xml.etree.ElementTree as ET
import os
if not os.path.exists('descriptions'):
    os.makedirs('descriptions')
inf='HL.osm'
outf='HL_ref.osm'
# Parse the OSM file
tree = ET.parse(inf)
root = tree.getroot()
# Counter for generating unique file names
counter = 1
# Find all tag elements with k="description"
for tag in root.findall(".//tag[@k='description']"):
    # Get the original description
    original_description = tag.get('v')
    # Generate a unique filename
    filename = f"desc_{counter}.html"
    # Save the original description to an HTML file
    with open(os.path.join('descriptions', filename), 'w', encoding='utf-8') as f:
        f.write(original_description)
    # Replace the original description with a reference
    tag.set('v', f"See {filename}")
    # Increment the counter
    counter += 1
# Write the modified XML back to a file
tree.write(outf, encoding='utf-8', xml_declaration=True)
print(f"Processed {counter-1} description tags. to {outf}")
