# Bundled ICC profiles — third-party notices

mPDF ships the ICC profiles in this directory as **verbatim, unmodified** data
files. Each is covered by its own licence (below), independently of mPDF's own
licence — they are aggregated data assets, not part of mPDF's source code, and
are redistributed here under the terms their publishers grant. Do not alter these
files; ship them byte-for-byte.

To use a different output condition (e.g. your house print profile, or a CMYK
output intent for PDF/X-4), set the `ICCProfile` config key to the path of your
own `.icc` file — it overrides the bundled default and nothing here is embedded.

---

## sRGB_IEC61966-2-1.icc

- **Colour space:** RGB. Used as the PDF/A output-intent profile, and as the
  embedded `/DestOutputProfile` (and transparency-group blending colour space)
  for **PDF/X-4** documents that do not supply their own `ICCProfile`. Also the
  ICC-based colour space RGB is written in where a PDF/X-4 document prints to a
  CMYK output intent, which permits no DeviceRGB.
- **Publisher / copyright:** International Color Consortium.
- **Embedded copyright string:** `Copyright International Color Consortium, 2009`.
- **Terms:** Distributed by the ICC as a freely redistributable reference
  profile; may be copied, embedded and redistributed without restriction.
- **Source:** <https://www.color.org/srgbprofiles.xalter>

No CMYK profile is bundled: every CMYK profile in the ICC registry is between
2.7 MB and 3.7 MB, which is several times the size of mPDF itself. PDF/X-4
permits an RGB output intent, so documents that do not name a profile print to
sRGB; documents that must print to a press condition name that condition's
profile through `ICCProfile`.
