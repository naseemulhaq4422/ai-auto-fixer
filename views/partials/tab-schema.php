<?php
/**
 * Partial: Tab Schema Ownership & AI Crawler Registry (GEO/AEO).
 *
 * @package AiAutoFixer
 */

// Strict defensive check: Prevent direct script access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aaf-schema-wrap">
	<div class="aaf-section-header">
		<h2><?php esc_html_e( 'Schema Ownership & AI Search Crawler Registry (GEO / AEO)', 'ai-auto-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Detects existing Schema managers to prevent duplicate markup conflicts. Manages access permissions for OpenAI, Anthropic, and Perplexity crawlers.', 'ai-auto-fixer' ); ?></p>
	</div>

	<!-- AI Bot Crawler Controls -->
	<div class="aaf-card aaf-crawler-card">
		<div class="aaf-card-header">
			<h3><?php esc_html_e( 'AI Search Engine Crawlers (Generative Engine Optimization)', 'ai-auto-fixer' ); ?></h3>
			<p><?php esc_html_e( 'Allowing reputable AI crawlers increases your brand and content citations in ChatGPT, Claude, and Perplexity search answers.', 'ai-auto-fixer' ); ?></p>
		</div>
		<div class="aaf-card-body">
			<div class="aaf-bot-grid">
				<div class="aaf-bot-item">
					<div class="aaf-bot-info">
						<strong>GPTBot</strong>
						<span class="aaf-bot-desc">OpenAI ChatGPT Web Crawler</span>
					</div>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Allowed', 'ai-auto-fixer' ); ?></span>
				</div>
				<div class="aaf-bot-item">
					<div class="aaf-bot-info">
						<strong>ClaudeBot</strong>
						<span class="aaf-bot-desc">Anthropic Claude AI Crawler</span>
					</div>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Allowed', 'ai-auto-fixer' ); ?></span>
				</div>
				<div class="aaf-bot-item">
					<div class="aaf-bot-info">
						<strong>PerplexityBot</strong>
						<span class="aaf-bot-desc">Perplexity AI Answer Engine</span>
					</div>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Allowed', 'ai-auto-fixer' ); ?></span>
				</div>
				<div class="aaf-bot-item">
					<div class="aaf-bot-info">
						<strong>CCBot</strong>
						<span class="aaf-bot-desc">Common Crawl LLM Dataset</span>
					</div>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Allowed', 'ai-auto-fixer' ); ?></span>
				</div>
				<div class="aaf-bot-item">
					<div class="aaf-bot-info">
						<strong>Google-Extended</strong>
						<span class="aaf-bot-desc">Google Gemini AI Training</span>
					</div>
					<span class="aaf-badge aaf-badge-passed"><?php esc_html_e( 'Allowed', 'ai-auto-fixer' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Schema Ownership Card -->
	<div class="aaf-card" style="margin-top: 24px;">
		<div class="aaf-card-header">
			<h3><?php esc_html_e( 'Structured Data Ownership & Conflict Matrix', 'ai-auto-fixer' ); ?></h3>
			<p><?php esc_html_e( 'Respects existing SEO plugins (Yoast, Rank Math, AIOSEO) to strictly eliminate duplicate schema entities.', 'ai-auto-fixer' ); ?></p>
		</div>
		<div class="aaf-card-body">
			<table class="aaf-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Entity Type', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'Managing Origin', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'Ownership State', 'ai-auto-fixer' ); ?></th>
						<th><?php esc_html_e( 'Auto-Fixer Behavior', 'ai-auto-fixer' ); ?></th>
					</tr>
				</thead>
				<tbody id="aaf-schema-tbody">
					<tr>
						<td><code>Organization</code></td>
						<td><span id="aaf-schema-org-source"><?php esc_html_e( 'Checking...', 'ai-auto-fixer' ); ?></span></td>
						<td><span class="aaf-badge" id="aaf-schema-org-state"><?php esc_html_e( 'Evaluating', 'ai-auto-fixer' ); ?></span></td>
						<td id="aaf-schema-org-action"><?php esc_html_e( 'Auditor mode only (No injection)', 'ai-auto-fixer' ); ?></td>
					</tr>
					<tr>
						<td><code>WebSite</code></td>
						<td><span id="aaf-schema-site-source"><?php esc_html_e( 'Checking...', 'ai-auto-fixer' ); ?></span></td>
						<td><span class="aaf-badge" id="aaf-schema-site-state"><?php esc_html_e( 'Evaluating', 'ai-auto-fixer' ); ?></span></td>
						<td id="aaf-schema-site-action"><?php esc_html_e( 'Auditor mode only', 'ai-auto-fixer' ); ?></td>
					</tr>
					<tr>
						<td><code>BreadcrumbList</code></td>
						<td><span id="aaf-schema-bread-source"><?php esc_html_e( 'Checking...', 'ai-auto-fixer' ); ?></span></td>
						<td><span class="aaf-badge" id="aaf-schema-bread-state"><?php esc_html_e( 'Evaluating', 'ai-auto-fixer' ); ?></span></td>
						<td id="aaf-schema-bread-action"><?php esc_html_e( 'Auditor mode only', 'ai-auto-fixer' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
