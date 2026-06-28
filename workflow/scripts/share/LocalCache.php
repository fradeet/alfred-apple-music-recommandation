<?php


// Codex TODO 获取缓存函数
// 输入：文件 URL
// 输出：缓存文件路径或空
function checkFileCache(string $url, string $cacheDir): ?string
{
	// TODO 检测 URL 是否已缓存，文件按类型 + ID 存储
	// 通过在目录中检测图片文件名
	// 如果有则直接返回缓存文件路径
	return value;
}

// Codex TODO 并行缓存函数
// 输入：数组：URL，缓存目录
// 输出：缓存文件路径数组
function cacheFilesParallel(array $urls, string $cacheDir): array
{
    // 先从 URL 中获得文件名
    // 先检查整个列表，使用文件名检查缓存目录中是否有目标图片，如果有则直接返回路径 checkFileCache()
    // 将没有下载整理为列表，使用 cURL 并行下载，并直接储存到目录中，返回路径
    // 超时时间 60 秒，并发 5
    // 下载失败的统一返回空路径

}
