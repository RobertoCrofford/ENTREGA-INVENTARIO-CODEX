import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "C:/Users/NachoCT/Downloads/Proyecto trabajo/ENTREGA-INVENTARIO-CODEX/outputs/plantilla-importacion-activos";
const outputPath = `${outputDir}/plantilla-importacion-activos.xlsx`;
const workbook = Workbook.create();

const assets = workbook.worksheets.add("Activos");
assets.showGridLines = false;

const headers = [[
  "Activo fijo",
  "Numero de serie",
  "ubicación",
  "Denominación del activo fijo",
  "Ce.coste",
  "Cantidad",
  "Fe.capit.",
  "Valor Neto",
  "Vida Restante",
  "Encargado",
]];
assets.getRange("A1:J1").values = headers;
assets.getRange("A2:J2").values = [[
  "500000001",
  "SN-EJEMPLO-001",
  "PASTORAL",
  "Notebook Lenovo ThinkPad E14",
  "1617101",
  1,
  "2026-01-31",
  450000,
  36,
  "Nombre del encargado",
]];
assets.getRange("A1:J1").format = {
  fill: "#4C1D95",
  font: { bold: true, color: "#FFFFFF" },
  horizontalAlignment: "center",
  verticalAlignment: "center",
  wrapText: true,
  borders: { preset: "outside", style: "thin", color: "#7E22CE" },
};
assets.getRange("A2:J2").format = {
  fill: "#F3E8FF",
  font: { color: "#4C1D95", italic: true },
  verticalAlignment: "center",
  wrapText: true,
  borders: { preset: "outside", style: "thin", color: "#DDD6FE" },
};
assets.getRange("A1:J2").format.rowHeight = 28;
assets.getRange("A1").format.columnWidth = 16;
assets.getRange("B1").format.columnWidth = 20;
assets.getRange("C1").format.columnWidth = 18;
assets.getRange("D1").format.columnWidth = 42;
assets.getRange("E1").format.columnWidth = 14;
assets.getRange("F1").format.columnWidth = 11;
assets.getRange("G1").format.columnWidth = 14;
assets.getRange("H1").format.columnWidth = 16;
assets.getRange("I1").format.columnWidth = 16;
assets.getRange("J1").format.columnWidth = 24;
assets.getRange("A2:C2").format.numberFormat = "@";
assets.getRange("F2").format.numberFormat = "0";
assets.getRange("H2").format.numberFormat = "#,##0";
assets.freezePanes.freezeRows(1);

const guide = workbook.worksheets.add("Instrucciones");
guide.showGridLines = false;
guide.getRange("A1:B1").merge();
guide.getRange("A1").values = [["Plantilla para importar activos"]];
guide.getRange("A1:B1").format = {
  fill: "#4C1D95",
  font: { bold: true, color: "#FFFFFF", size: 14 },
  horizontalAlignment: "center",
  verticalAlignment: "center",
};
guide.getRange("A3:B9").values = [
  ["Paso", "Indicaciones"],
  ["1", "En la hoja Activos, reemplaza la fila de ejemplo por tus datos."],
  ["2", "No cambies los encabezados de la fila 1."],
  ["3", "Cada fila debe tener un Activo fijo único."],
  ["4", "Usa la ubicación real: por ejemplo PASTORAL, 17 o Sala 101."],
  ["5", "Cantidad no crea activos adicionales: cada fila representa un activo."],
  ["6", "Guarda el archivo como .xlsx y súbelo desde Importaciones."],
];
guide.getRange("A3:B3").format = {
  fill: "#7E22CE",
  font: { bold: true, color: "#FFFFFF" },
  horizontalAlignment: "center",
  borders: { preset: "outside", style: "thin", color: "#7E22CE" },
};
guide.getRange("A4:B9").format = {
  wrapText: true,
  verticalAlignment: "center",
  borders: { preset: "inside", style: "thin", color: "#E9D5FF" },
};
guide.getRange("A4:A9").format = { fill: "#F3E8FF", horizontalAlignment: "center", font: { bold: true, color: "#4C1D95" } };
guide.getRange("A1").format.rowHeight = 30;
guide.getRange("A3:B9").format.rowHeight = 28;
guide.getRange("A1").format.columnWidth = 12;
guide.getRange("B1").format.columnWidth = 70;

const check = await workbook.inspect({ kind: "table", range: "Activos!A1:J2", include: "values", tableMaxRows: 3, tableMaxCols: 10 });
console.log(check.ndjson);
const render = await workbook.render({ sheetName: "Activos", range: "A1:J2", scale: 2, format: "png" });
await fs.writeFile(`${outputDir}/preview.png`, new Uint8Array(await render.arrayBuffer()));
const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);
