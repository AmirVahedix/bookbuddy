import sys
import json
from pypdf import PdfReader

def extract_outline(pdf_path):
    try:
        reader = PdfReader(pdf_path)
    except Exception as e:
        print(json.dumps({"error": f"Failed to read PDF: {str(e)}"}))
        return

    # Check if outline is present
    try:
        outline = reader.outline
    except Exception as e:
        outline = None

    if not outline:
        print(json.dumps({"sections": []}))
        return

    flat_sections = []

    def process_item(item, level=1):
        if isinstance(item, list):
            for sub_item in item:
                process_item(sub_item, level + 1)
        else:
            try:
                title = item.title
                page_num = reader.get_destination_page_number(item)
                flat_sections.append({
                    "title": title,
                    "page": page_num + 1,  # 1-based page number
                    "level": level
                })
            except Exception as e:
                # Skip if page cannot be resolved
                pass

    for item in outline:
        process_item(item, level=1)

    total_pages = len(reader.pages)
    for i in range(len(flat_sections)):
        curr_page = flat_sections[i]["page"]
        curr_level = flat_sections[i]["level"]
        
        # Default end_page is total_pages
        end_page = total_pages
        
        # Look for the next section that terminates this section.
        # It must be at the same or higher hierarchical level (i.e. level <= curr_level)
        # and start on a page >= curr_page.
        for j in range(i + 1, len(flat_sections)):
            next_page = flat_sections[j]["page"]
            next_level = flat_sections[j]["level"]
            
            if next_level <= curr_level:
                if next_page > curr_page:
                    end_page = next_page - 1
                    break
                elif next_page == curr_page:
                    end_page = curr_page
                    break
        
        flat_sections[i]["end_page"] = end_page

    print(json.dumps({"sections": flat_sections}))

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No PDF path provided"}))
        sys.exit(1)
    extract_outline(sys.argv[1])
