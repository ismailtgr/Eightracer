"""Bounded JSON stdin -> private aggregate PDF stdout. No network, HTML, or user file paths."""
import io
import json
import pathlib
import sys
from xml.sax.saxutils import escape

import reportlab
from reportlab.lib import colors
from reportlab.lib.enums import TA_RIGHT
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, LongTable, TableStyle, PageBreak


def render(data):
    if len(data.get('penerimaan', [])) > 1000 or len(data.get('keuangan', {}).get('per_kampus_prodi', [])) > 1000:
        raise ValueError('Report exceeds row limit')
    fontdir = pathlib.Path(reportlab.__file__).parent / 'fonts'
    pdfmetrics.registerFont(TTFont('Book', str(fontdir / 'Vera.ttf')))
    pdfmetrics.registerFont(TTFont('BookBold', str(fontdir / 'VeraBd.ttf')))
    styles = getSampleStyleSheet()
    for name in ['Normal', 'BodyText', 'Title', 'Heading1', 'Heading2']:
        styles[name].fontName = 'BookBold' if name in ['Title', 'Heading1', 'Heading2'] else 'Book'
    styles['Title'].fontSize = 23
    styles['Title'].leading = 29
    styles['BodyText'].leading = 15
    styles.add(ParagraphStyle('Cell', fontName='Book', fontSize=8, leading=11))
    styles.add(ParagraphStyle('HeadCell', fontName='BookBold', fontSize=8, leading=11))
    story = []
    def p(value, style='BodyText'):
        return Paragraph(escape(str(value if value is not None else 'Belum tercatat')), styles[style])
    def table(headers, rows, widths):
        cells = [[p(h, 'HeadCell') for h in headers]] + [[p(c, 'Cell') for c in row] for row in rows]
        t = LongTable(cells, colWidths=widths, repeatRows=1, hAlign='LEFT')
        t.setStyle(TableStyle([('GRID', (0, 0), (-1, -1), .4, colors.black), ('VALIGN', (0, 0), (-1, -1), 'TOP'), ('LEFTPADDING', (0, 0), (-1, -1), 7), ('RIGHTPADDING', (0, 0), (-1, -1), 7), ('TOPPADDING', (0, 0), (-1, -1), 7), ('BOTTOMPADDING', (0, 0), (-1, -1), 7)]))
        story.append(t)
    def rupiah(v):
        return 'Belum tercatat' if v is None else 'Rp ' + f'{float(v):,.2f}'.replace(',', '_').replace('.', ',').replace('_', '.')
    story += [p(data.get('title', 'Eightracer - Buku Alumni'), 'Title'), Spacer(1, 8*mm), p('Dokumentasi agregat sekolah - akses admin/Guru BK'), p('Dibuat: ' + str(data.get('as_of', ''))), Spacer(1, 7*mm)]
    filters = data.get('filters') or {}
    story.append(p('Filter: ' + ('; '.join(f'{k} = {v}' for k, v in filters.items()) or 'Seluruh data yang tercatat')))
    story += [Spacer(1, 8*mm), p('Ringkasan', 'Heading1'), p(f"Alumni unik dengan penerimaan tercatat: {data.get('jumlah_alumni_penerimaan', 0)}"), p(f"Alumni dengan progres keuangan sesuai filter: {data.get('keuangan', {}).get('jumlah_alumni', 0)}"), Spacer(1, 5*mm), p('Data yang belum tersedia tetap dinyatakan belum tercatat. Laporan ini tidak mencakup seluruh populasi perguruan tinggi. Satu alumni dapat memiliki lebih dari satu penerimaan; jumlah kelompok tidak selalu dapat dijumlahkan menjadi alumni unik.')]
    story += [Spacer(1, 8*mm), p('Penerimaan perguruan tinggi', 'Heading1')]
    admissions = data.get('penerimaan', [])
    if admissions:
        table(['Universitas', 'Program studi', 'Jalur', 'Lulus', 'Alumni'], [[r['universitas'], r['program_studi'], r['jalur_masuk'], r.get('tahun_lulus'), r['jumlah_alumni']] for r in admissions], [53*mm, 50*mm, 27*mm, 19*mm, 19*mm])
    else:
        story.append(p('Belum ada penerimaan sesuai filter.'))
    story += [PageBreak(), p('UKT dan IPK alumni', 'Heading1'), p('Snapshot menggunakan progres terakhir tiap alumni. UKT kosong tidak dimasukkan sebagai nol. Ringkasan berikut merupakan data alumni sekolah yang tercatat.'), Spacer(1, 5*mm)]
    finances = data.get('keuangan', {}).get('per_kampus_prodi', [])
    if finances:
        table(['Kampus / prodi', 'Alumni', 'UKT minimum', 'UKT maksimum', 'IPK rerata'], [[r['universitas']+' / '+r['program_studi'], r['jumlah_alumni'], rupiah(r['ukt']['minimum']), rupiah(r['ukt']['maksimum']), r['ipk']['rata_rata']] for r in finances], [58*mm, 17*mm, 35*mm, 35*mm, 23*mm])
    else:
        story.append(p('Belum ada progres UKT/IPK sesuai filter. Data penerimaan saja tidak cukup untuk menyimpulkan biaya kuliah atau penerimaan beasiswa.'))
    story += [Spacer(1, 8*mm), p('Perkembangan IPK per semester', 'Heading2')]
    trends = data.get('keuangan', {}).get('tren_ipk', [])
    if len(trends) > 1000:
        story.append(p('Terlalu banyak kelompok tren untuk buku ini. Gunakan filter lebih spesifik atau API dashboard.'))
    elif trends:
        table(['Kampus / prodi', 'Semester', 'Sampel IPK', 'IPK rerata'], [[r['universitas']+' / '+r['program_studi'], r['semester'], r['ipk']['jumlah_diketahui'], r['ipk']['rata_rata']] for r in trends], [95*mm, 23*mm, 25*mm, 25*mm])
    else:
        story.append(p('Belum ada nilai IPK semester yang tercatat.'))
    story += [Spacer(1, 8*mm), p('Catatan penggunaan', 'Heading2'), p('Buku berisi agregat, tanpa NISN, kontak, penghasilan orang tua, identitas atau bukti privat murid. Izin publikasi individual tetap mengikuti alur tersendiri. Gunakan sumber resmi penyelenggara untuk keputusan biaya dan beasiswa.')]
    output = io.BytesIO()
    def footer(canvas, doc):
        canvas.saveState(); canvas.setFont('Book', 8); canvas.line(21*mm, 17*mm, 189*mm, 17*mm); canvas.drawString(21*mm, 12*mm, 'CONTOH DATA SINTETIS | Eightracer' if data.get('example') else 'Eightracer | Dokumen agregat sekolah'); canvas.drawRightString(189*mm, 12*mm, str(doc.page)); canvas.restoreState()
    doc = SimpleDocTemplate(output, pagesize=(210*mm, 297*mm), rightMargin=21*mm, leftMargin=21*mm, topMargin=21*mm, bottomMargin=24*mm, title='Buku Digital Alumni Eightracer', author='Eightracer')
    doc.build(story, onFirstPage=footer, onLaterPages=footer)
    return output.getvalue()


if __name__ == '__main__':
    try:
        raw = sys.stdin.buffer.read(2*1024*1024+1)
        if len(raw) > 2*1024*1024:
            raise ValueError('Input too large')
        sys.stdout.buffer.write(render(json.loads(raw)))
    except Exception:
        sys.stderr.write('PDF generation failed.\n')
        raise SystemExit(1)
