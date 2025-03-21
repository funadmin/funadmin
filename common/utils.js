// 工具函数：生成分享图片（示例）
export function generateShareImage(cardData) {
  const canvasContext = uni.createCanvasContext('shareCanvas');
  canvasContext.setFillStyle('#ffffff');
  canvasContext.fillRect(0, 0, 300, 400);

  canvasContext.setFontSize(20);
  canvasContext.setFillStyle('#000000');
  canvasContext.fillText(`姓名: ${cardData.name}`, 10, 50);
  canvasContext.fillText(`职位: ${cardData.position}`, 10, 100);
  canvasContext.fillText(`公司: ${cardData.company}`, 10, 150);
  canvasContext.fillText(`电话: ${cardData.phone}`, 10, 200);

  canvasContext.draw();
}