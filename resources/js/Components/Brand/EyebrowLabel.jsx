/** * Eyebrow label — small uppercase tracking-wider tag that sits above headings * and section titles. Renders the BukuCloud brand voice. */
export default function EyebrowLabel({ children, tone = 'terracotta', as: Tag = 'p', className = '', ...props
}) { const toneClass = { terracotta: 'text-terracotta', forest: 'text-forest dark:text-forest-light', ink: 'text-ink-muted', mustard: 'text-mustard', }[tone] || 'text-terracotta'; return ( <Tag className={`text-eyebrow font-semibold uppercase ${toneClass} ${className}`} {...props} > {children} </Tag> );
}
