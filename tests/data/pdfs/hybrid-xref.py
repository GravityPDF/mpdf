"""Make hybrid-xref.pdf from object-streams-png-predictor.pdf

A hybrid-reference file (ISO 32000-1, 7.5.8.4) has a classic cross-reference table for readers of PDF 1.4, and its
trailer's /XRefStm points at a cross-reference stream that lists the objects kept in object streams, which the table
marks free. This keeps object-streams-png-predictor.pdf as it is up to its cross-reference stream, so every object stays at the
same offset, then writes the two in that stream's place.

The source's cross-reference stream is read here with zlib, not with mPDF, so the file does not depend on the code it
tests.

    python3 hybrid-xref.py    (in tests/data/pdfs)
"""
import re
import zlib

source = open('object-streams-png-predictor.pdf', 'rb').read()

# The cross-reference stream startxref points at: a PNG Up predicted Flate stream
startxref = int(re.search(rb'startxref\s+(\d+)\s+%%EOF\s*$', source).group(1))
match = re.compile(rb'(\d+) 0 obj\s*<<(.*?)>>\s*stream\r?\n', re.S).match(source, startxref)
number, dictionary = int(match.group(1)), match.group(2)
assert b'/Predictor 12' in dictionary
widths = [int(w) for w in re.search(rb'/W \[ ?(\d+) (\d+) (\d+) ?\]', dictionary).groups()]
length = int(re.search(rb'/Length (\d+)', dictionary).group(1))
data = zlib.decompress(source[match.end():match.end() + length])

columns = sum(widths)
rows = []
previous = bytes(columns)
for i in range(0, len(data), columns + 1):
    row = bytes((data[i + 1 + k] + previous[k]) & 0xFF for k in range(columns))
    previous = row
    fields, position = [], 0
    for width in widths:
        fields.append(int.from_bytes(row[position:position + width], 'big'))
        position += width
    rows.append(fields)

trailer = b' '.join(re.search(rb'(' + key + rb' [^/]*)', dictionary).group(1).strip() for key in (rb'/Root', rb'/Info', rb'/ID'))
compressed = [n for n, row in enumerate(rows) if row[0] == 2]
assert compressed == list(range(compressed[0], compressed[-1] + 1)), 'one run of compressed objects'

pdf = source[:startxref]

# The cross-reference stream, listing only the compressed objects: Flate without a predictor
stream = zlib.compress(b''.join(bytes([2, rows[n][1], rows[n][2]]) for n in compressed))
offsets = {n: row[1] for n, row in enumerate(rows) if row[0] == 1}
offsets[number] = len(pdf)
pdf += b'%d 0 obj\n<< /Type /XRef /Size %d /Index [%d %d] /W [1 1 1] /Filter /FlateDecode /Length %d >>\nstream\n' % (
    number, len(rows), compressed[0], len(compressed), len(stream))
pdf += stream + b'\nendstream\nendobj\n'

# The classic table: the objects written in the file, in subsections that leave the compressed ones out, as the
# example in 7.5.8.4 does. Marked free instead, readers take them as deleted and do not look in the stream.
table = len(pdf)
pdf += b'xref\n0 %d\n0000000000 65535 f \n' % compressed[0]
for n in range(1, compressed[0]):
    pdf += b'%010d 00000 n \n' % offsets[n]
pdf += b'%d %d\n' % (compressed[-1] + 1, len(rows) - compressed[-1] - 1)
for n in range(compressed[-1] + 1, len(rows)):
    pdf += b'%010d 00000 n \n' % offsets[n]
pdf += b'trailer\n<< /Size %d %s /XRefStm %d >>\nstartxref\n%d\n%%%%EOF\n' % (len(rows), trailer, offsets[number], table)

open('hybrid-xref.pdf', 'wb').write(pdf)
print('hybrid-xref.pdf: %d bytes, %d objects in object streams' % (len(pdf), len(compressed)))
