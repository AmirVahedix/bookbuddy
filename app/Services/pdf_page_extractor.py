import sys
import json
from pypdf import PdfReader, PdfWriter

def extract_pages(input_path, output_path, pages_str):
    try:
        reader = PdfReader(input_path)
        writer = PdfWriter()
        
        # Parse page numbers (1-based, e.g. "5,6,7,8,9,10")
        pages = [int(p.strip()) for p in pages_str.split(',') if p.strip()]
        
        for p in pages:
            # pypdf is 0-indexed
            idx = p - 1
            if 0 <= idx < len(reader.pages):
                writer.add_page(reader.pages[idx])
                
        with open(output_path, 'wb') as out_f:
            writer.write(out_f)
            
        print(json.dumps({"success": True}))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print(json.dumps({"error": "Usage: python pdf_page_extractor.py <input> <output> <pages_csv>"}))
        sys.exit(1)
    extract_pages(sys.argv[1], sys.argv[2], sys.argv[3])
