# PDFs from other producers

Where the PDFs used to test importing with a cross-reference stream came from. None was written by mPDF.

| File | Producer | Cross-reference |
|---|---|---|
| `compressed-xref.pdf` | Adobe Acrobat 11 (Web Capture) | Linearized: two cross-reference streams chained by `/Prev`, with `/Index` subsections and a PNG predictor, `/W [1 2 1]`; object streams |
| `object-streams-png-predictor.pdf` | qpdf 12.2 | One cross-reference stream with a PNG predictor, `/W [1 2 1]`; one object stream |
| `object-streams-no-predictor.pdf` | Ghostscript 10.04 | One cross-reference stream without a predictor, `/W [1 2 2]`, `/Index`; one object stream |
| `hybrid-xref.pdf` | `hybrid-xref.py`, from `object-streams-png-predictor.pdf` | Hybrid: a classic table in two subsections for the objects written in the file, and `/XRefStm` for a cross-reference stream without a predictor, `/W [1 1 1]`, `/Index`, listing the objects in the object stream (the catalog and the page tree among them) |

`object-streams-png-predictor.pdf` and `object-streams-no-predictor.pdf` were made from `2-Page-PDF_1_4.pdf` (Acrobat Distiller):

```
python3 -c "import pikepdf; pikepdf.open('2-Page-PDF_1_4.pdf').save('object-streams-png-predictor.pdf', object_stream_mode=pikepdf.ObjectStreamMode.generate)"
gs -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -dWriteObjStms=true -dWriteXRefStm=true -sOutputFile=object-streams-no-predictor.pdf 2-Page-PDF_1_4.pdf
```

No producer to hand writes a hybrid-reference file, so `hybrid-xref.py` makes one: it keeps `object-streams-png-predictor.pdf` as it is up to its cross-reference stream, then writes a cross-reference stream for the objects in the object stream and a classic table for the rest, which leaves them out as the example in ISO 32000-1, 7.5.8.4 does. It reads the source with zlib rather than with mPDF. Run it in this directory:

```
python3 hybrid-xref.py
```
