# ICC profile fixtures

`rgb-printer-header.icc` is an ICC version 2.1 profile header of the output
device (`prtr`) class, printing to RGB, with a tag table of no tags. It was
generated for the tests, carries no colour data and no third-party licence,
and gives a PDF/X-4 document an RGB output intent without embedding a real
profile. mPDF reads only the header of an output intent profile, to count its
colour components and check its class.
