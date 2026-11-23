"""School analytics JSON to a paginated, private PDF. Input and output are binary streams."""
import io
import json
import math
import re
import sys
from datetime import datetime
from html import escape
from pathlib import Path

import reportlab
from reportlab.lib import colors
from reportlab.lib.enums import TA_RIGHT
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import LongTable, PageBreak, Paragraph, SimpleDocTemplate, Spacer, TableStyle


def number(value, decimals=0):
    if value is None or isinstance(value, bool):
        return "-"
    try:
        numeric = float(value)
        if not math.isfinite(numeric):
            return "-"
        return f"{numeric:,.{decimals}f}".replace(",", "_").replace(".", ",").replace("_", ".")
    except (ValueError, TypeError):
        return "-"


def money(value):
    formatted = number(value)
    return "Rp " + formatted if formatted != "-" else "-"


def render(data):
    if not isinstance(data, dict):
        raise ValueError("Invalid report")
    summary, financial = data["summary"], data["financial"]
    fonts = Path(reportlab.__file__).parent / "fonts"
    pdfmetrics.registerFont(TTFont("School", str(fonts / "Vera.ttf")))
    pdfmetrics.registerFont(TTFont("SchoolBold", str(fonts / "VeraBd.ttf")))
    styles = getSampleStyleSheet()
    for style in styles.byName.values():
        style.fontName = "School"
    styles.add(ParagraphStyle("ReportTitle", fontName="SchoolBold", fontSize=21, leading=27, spaceAfter=8))
    styles.add(ParagraphStyle("SectionTitle", fontName="SchoolBold", fontSize=13, leading=18, spaceBefore=16, spaceAfter=9, keepWithNext=True))
    styles.add(ParagraphStyle("ReportBody", fontSize=9, leading=14, spaceAfter=6))
    styles.add(ParagraphStyle("Cell", fontSize=8, leading=11, splitLongWords=True))
    styles.add(ParagraphStyle("HeadCell", fontName="SchoolBold", fontSize=8, leading=11, textColor=colors.white))
    styles.add(ParagraphStyle("NumericCell", parent=styles["Cell"], alignment=TA_RIGHT))

    def paragraph(value, style="ReportBody"):
        text = "-" if value is None or value == "" else str(value)
        text = re.sub(r"[\u2010-\u2015\u2212]", "-", text)
        return Paragraph(escape(text).replace("\n", "<br/>"), styles[style])

    story = []

    def heading(title):
        story.append(paragraph(title, "SectionTitle"))

    def table(headers, rows, widths, numeric=()):
        if not rows:
            story.append(paragraph("Tidak ada data sesuai filter yang dipilih."))
            return
        cells = [[paragraph(value, "HeadCell") for value in headers]]
        cells.extend([[paragraph(value, "NumericCell" if index in numeric else "Cell") for index, value in enumerate(row)] for row in rows])
        report_table = LongTable(cells, colWidths=[width * mm for width in widths], repeatRows=1, hAlign="LEFT", splitByRow=1, splitInRow=1)
        report_table.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#4D246C")),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F6F3F9")]),
            ("LINEBELOW", (0, 0), (-1, 0), 0.6, colors.HexColor("#4D246C")),
            ("LINEBELOW", (0, 1), (-1, -1), 0.3, colors.HexColor("#DDD7E3")),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 6),
            ("RIGHTPADDING", (0, 0), (-1, -1), 6),
            ("TOPPADDING", (0, 0), (-1, -1), 7),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ]))
        story.extend([report_table, Spacer(1, 3 * mm)])

    timestamp = str(data.get("as_of", ""))
    try:
        timestamp = datetime.fromisoformat(timestamp).strftime("%d-%m-%Y, %H.%M WIB")
    except ValueError:
        timestamp = "-"
    story.extend([paragraph("EIGHTRACER - SMAN 8 MALANG"), paragraph("Laporan Analitik Beasiswa", "ReportTitle"), paragraph("Data per " + timestamp)])
    filters = data.get("applied_filters") or {}
    labels = {"angkatan": "Tahun lulus", "prodi": "Program studi", "program_studi": "Program studi", "perguruan_tinggi_id": "Perguruan tinggi", "beasiswa_id": "Beasiswa", "beasiswa": "Beasiswa", "periode": "Periode progres"}
    scholarship_names = {str(row["value"]): row["label"] for row in data.get("filters", {}).get("scholarships", [])}
    applied = []
    for field, value in filters.items():
        if field in labels and value not in (None, ""):
            if field in ("beasiswa", "beasiswa_id"):
                value = scholarship_names.get(str(value), str(value))
            applied.append(f"{labels[field]}: {value}")
    story.append(paragraph("Filter laporan: " + (" | ".join(applied) if applied else "Seluruh data sekolah")))
    heading("Ringkasan Alumni dan Bantuan Pendidikan")
    indicators = [("Profil alumni", "alumni"), ("Alumni dengan penerimaan perguruan tinggi", "admitted_alumni"), ("Catatan penerimaan perguruan tinggi", "admissions"), ("Penerima KIP / KIP-K", "kip"), ("Penerima beasiswa selain KIP", "scholarship_non_kip"), ("Alumni dengan bantuan pendidikan (unik)", "all_aid")]
    table(["Indikator", "Jumlah Alumni / Catatan"], [[label, number(summary.get(key))] for label, key in indicators], [125, 45], numeric=(1,))
    heading("Distribusi Beasiswa")
    table(["No.", "Program Beasiswa / Bantuan", "Alumni"], [[index, row["label"], number(row["count"])] for index, row in enumerate(data.get("scholarships", []), 1)], [10, 130, 30], numeric=(2,))
    heading("Penerima KIP / KIP-K per Tahun Lulus")
    table(["Tahun Lulus", "Alumni"], [[row["label"], number(row["count"])] for row in data.get("kip_by_cohort", [])], [125, 45], numeric=(1,))
    heading("Ringkasan UKT dan IPK")
    rows = []
    for name, field, formatter in [("UKT", "ukt", money), ("IPK", "ipk", lambda value: number(value, 2))]:
        stats = financial.get(field, {})
        rows.append([name, number(stats.get("known")), number(stats.get("unknown")), formatter(stats.get("minimum")), formatter(stats.get("maximum")), formatter(stats.get("mean"))])
    table(["Data", "Diketahui", "Belum Ada", "Minimum", "Maksimum", "Rata-rata"], rows, [19, 21, 25, 35, 35, 35], numeric=(1, 2, 3, 4, 5))

    story.append(PageBreak())
    heading("Daftar Perguruan Tinggi dan Program Studi")
    ranges = financial.get("ranges", [])
    if len(ranges) > 1000:
        raise ValueError("Too many groups")
    table(["No.", "Perguruan Tinggi", "Program Studi", "Alumni", "UKT Minimum", "UKT Maksimum"], [[index, row["university"], row["program"], number(row["count"]), money(row.get("stats", {}).get("minimum")), money(row.get("stats", {}).get("maximum"))] for index, row in enumerate(ranges, 1)], [10, 41, 43, 18, 29, 29], numeric=(3, 4, 5))
    heading("Perkembangan IPK per Periode")
    table(["Periode", "Sampel IPK", "IPK Minimum", "IPK Maksimum", "IPK Rata-rata"], [[row["label"], number(row["stats"].get("known")), number(row["stats"].get("minimum"), 2), number(row["stats"].get("maximum"), 2), number(row["stats"].get("mean"), 2)] for row in financial.get("ipk_trend", [])], [58, 25, 29, 29, 29], numeric=(1, 2, 3, 4))
    heading("Bantuan Pendidikan per Periode")
    table(["Periode", "Alumni Tercatat", "Penerima KIP-K"], [[row["label"], number(row["count"]), number(row["kipk"])] for row in data.get("periods", [])], [90, 40, 40], numeric=(1, 2))

    heading("Catatan Pembacaan Data")
    for index, note in enumerate(data.get("notes", []), 1):
        story.append(paragraph(f"{index}. {note}"))

    def footer(canvas, doc):
        canvas.saveState()
        canvas.setStrokeColor(colors.HexColor("#DDD7E3"))
        canvas.line(20 * mm, 17 * mm, 190 * mm, 17 * mm)
        canvas.setFont("School", 8)
        canvas.setFillColor(colors.HexColor("#665D70"))
        canvas.drawString(20 * mm, 12 * mm, "Eightracer | Analitik Beasiswa Sekolah")
        canvas.drawRightString(190 * mm, 12 * mm, f"Halaman {doc.page}")
        canvas.restoreState()

    output = io.BytesIO()
    document = SimpleDocTemplate(output, pagesize=(210 * mm, 297 * mm), leftMargin=20 * mm, rightMargin=20 * mm, topMargin=19 * mm, bottomMargin=24 * mm, title="Laporan Analitik Beasiswa Eightracer", author="SMAN 8 Malang - Eightracer")
    document.build(story, onFirstPage=footer, onLaterPages=footer)
    return output.getvalue()


if __name__ == "__main__":
    try:
        raw = sys.stdin.buffer.read(2 * 1024 * 1024 + 1)
        if len(raw) > 2 * 1024 * 1024:
            raise ValueError("Input too large")
        sys.stdout.buffer.write(render(json.loads(raw)))
    except Exception:
        sys.stderr.write("PDF generation failed.\n")
        raise SystemExit(1)
