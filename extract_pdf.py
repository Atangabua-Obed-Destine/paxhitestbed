import pdfplumber

pdf = pdfplumber.open('UB_Student_Record_Transition.pdf')
for page_num, page in enumerate(pdf.pages, 1):
    print(f"\n=== Page {page_num} ===\n")
    print(page.extract_text())
pdf.close()
