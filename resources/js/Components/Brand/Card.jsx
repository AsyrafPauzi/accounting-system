/** * Generic card wrapper. Defaults to surface bg with warm border and soft * rounding consistent with the rest of the brand. */
export default function Card({ children, className = '', padded = true, as: Tag = 'div', ...props
}) { return ( <Tag className={`bg-surface border border-border-warm rounded-2xl ${padded ? 'p-4 sm:p-6' : ''} ${className}`} {...props} > {children} </Tag> );
} export function CardHeader({ children, className = '', ...props }) { return ( <div className={`px-4 sm:px-6 py-4 border-b border-border-warm flex items-center justify-between ${className}`} {...props}> {children} </div> );
} export function CardBody({ children, className = '', ...props }) { return ( <div className={`p-4 sm:p-6 ${className}`} {...props}> {children} </div> );
}
