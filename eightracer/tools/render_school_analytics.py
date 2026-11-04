"""Render the same aggregate school data used by the authenticated FE."""
import io, json, re, sys
from html import escape
from pathlib import Path
import reportlab
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import SimpleDocTemplate, Paragraph, Table, TableStyle, Spacer, PageBreak

def render(data):
    fonts = Path(reportlab.__file__).parent / "fonts"
    pdfmetrics.registerFont(TTFont("School", str(fonts / "Vera.ttf")))
    pdfmetrics.registerFont(TTFont("SchoolBold", str(fonts / "VeraBd.ttf")))
    styles = getSampleStyleSheet()
    for style in styles.byName.values():
        style.fontName = "School"
    for key in ["Title","Heading1","Heading2"]:
        styles[key].fontName = "SchoolBold"
    styles.add(ParagraphStyle("Cell", fontName="School", fontSize=8, leading=11))
    styles["BodyText"].fontSize, styles["BodyText"].leading = 9, 13
    def p(value, style="BodyText"):
        text = re.sub("[\u2010-\u2015\u2212]", "-", str(value if value is not None else "Belum tercatat"))
        return Paragraph(escape(text), styles[style])
    def money(v):
        return "Belum tercatat" if v is None else "Rp " + f"{v:,.0f}".replace(",", ".")
    story = []
    def table(headers, rows, widths):
        t = Table([[p(v,"Cell") for v in headers]] + [[p(v,"Cell") for v in r] for r in rows], colWidths=widths, repeatRows=1, hAlign="LEFT")
        t.setStyle(TableStyle([("BACKGROUND",(0,0),(-1,0),colors.HexColor("#ececec")),
            ("LINEBELOW",(0,0),(-1,0),1,colors.black),("GRID",(0,0),(-1,-1),.3,colors.HexColor("#aaaaaa")),
            ("VALIGN",(0,0),(-1,-1),"TOP"),("TOPPADDING",(0,0),(-1,-1),6),("BOTTOMPADDING",(0,0),(-1,-1),6)]))
        story.append(t)
    s, f = data["summary"], data["financial"]
    story.extend([p("Eightracer","Title"),p("Analitik Beasiswa Sekolah","Heading1"),p("Data per " + data["as_of"]),
        p("Filter: " + (json.dumps(data.get("applied_filters",{}), ensure_ascii=False) if data.get("applied_filters") else "Seluruh data sekolah")),Spacer(1,5*mm)])
    table(["Indikator","Jumlah"],[
        ["Profil alumni lulusan",s["alumni"]],["Alumni dengan penerimaan PT",s["admitted_alumni"]],
        ["Catatan penerimaan PT",s["admissions"]],["Alumni dengan catatan beasiswa / KIP (unik)",s["all_aid"]],
        ["KIP / KIP-K sesuai catatan sekolah",s["kip"]],["Beasiswa selain KIP (alumni unik)",s["scholarship_non_kip"]]], [130*mm,38*mm])
    story.extend([Spacer(1,5*mm),p("Pembacaan sumber","Heading2")])
    for note in data["notes"]:
        story.append(p(note))
    story.extend([Spacer(1,5*mm),p("Distribusi program dan angkatan","Heading2")])
    if data["scholarships"]:
        table(["Beasiswa / indikator","Alumni"],[[v["label"],v["count"]] for v in data["scholarships"]],[130*mm,38*mm])
    else:
        story.append(p("Belum ada catatan beasiswa sesuai filter."))
    story.append(Spacer(1,3*mm))
    if data["kip_by_cohort"]:
        table(["Tahun lulus","KIP / KIP-K"],[[v["label"],v["count"]] for v in data["kip_by_cohort"]],[130*mm,38*mm])
    story.extend([Spacer(1,5*mm),p("UKT dan IPK yang diketahui","Heading1")])
    table(["Indikator","Diketahui","Belum tercatat","Minimum","Maksimum","Rata-rata"],[
        ["UKT",f["ukt"]["known"],f["ukt"]["unknown"],*[money(f["ukt"][k]) for k in ["minimum","maximum","mean"]]],
        ["IPK",f["ipk"]["known"],f["ipk"]["unknown"],*[f["ipk"][k] for k in ["minimum","maximum","mean"]]]],
        [20*mm,22*mm,30*mm,32*mm,32*mm,32*mm])
    story.extend([Spacer(1,5*mm),p("Penerimaan PT dan rentang UKT","Heading2"),
        p("Identitas kampus dan prodi tetap ditampilkan. Nilai kosong tidak dianggap nol. Alumni dengan beberapa penerimaan dapat muncul di beberapa kelompok."),Spacer(1,3*mm)])
    if len(f["ranges"])>1000:
        raise ValueError("Too many groups")
    if f["ranges"]:
        table(["Kampus","Program studi","Alumni","UKT minimum","UKT maksimum"],
            [[v["university"],v["program"],v["count"],money(v["stats"]["minimum"]),money(v["stats"]["maximum"])] for v in f["ranges"]],
            [49*mm,49*mm,16*mm,27*mm,27*mm])
    else:
        story.append(p("Belum ada penerimaan sesuai filter."))
    output = io.BytesIO()
    def footer(canvas, doc):
        canvas.saveState()
        canvas.setFont("School",8)
        canvas.line(21*mm,18*mm,189*mm,18*mm)
        canvas.drawString(21*mm,13*mm,"Eightracer | Agregat sekolah | Tanpa identitas pribadi")
        canvas.drawRightString(189*mm,13*mm,str(doc.page))
        canvas.restoreState()
    SimpleDocTemplate(output,pagesize=(210*mm,297*mm),leftMargin=21*mm,rightMargin=21*mm,topMargin=20*mm,bottomMargin=25*mm,
        title="Analitik Beasiswa Eightracer",author="Eightracer").build(story,onFirstPage=footer,onLaterPages=footer)
    return output.getvalue()

if __name__ == "__main__":
    try:
        raw=sys.stdin.buffer.read(2*1024*1024+1)
        if len(raw)>2*1024*1024:
            raise ValueError("Input too large")
        sys.stdout.buffer.write(render(json.loads(raw)))
    except Exception:
        sys.stderr.write("PDF generation failed.\n")
        raise SystemExit(1)
