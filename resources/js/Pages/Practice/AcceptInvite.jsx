import PracticeLayout from '@/Layouts/PracticeLayout';
import { Head, useForm } from '@inertiajs/react';

export default function AcceptInvite({ invitation }) {
    const { post, processing } = useForm();

    const accept = (e) => {
        e.preventDefault();
        post(route('firm.invite.accept.store', invitation.token));
    };

    return (
        <PracticeLayout>
            <Head title="Accept client invite" />

            <div className="max-w-xl">
                <p className="text-eyebrow font-semibold uppercase text-terracotta">Client invitation</p>
                <h1 className="font-display text-2xl lg:text-3xl font-medium text-ink tracking-tight mt-2">
                    Add {invitation.tenant_name} to your firm?
                </h1>
                <p className="text-ink-muted text-sm mt-3">
                    The team at <strong>{invitation.tenant_name}</strong> has invited your firm to manage their books with{' '}
                    <strong>{invitation.permission_level}</strong> access. They&apos;ll see your firm name in their settings; everything you do
                    will be logged against the firm.
                </p>

                <form onSubmit={accept} className="mt-6 flex gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-5 py-2.5 rounded-2xl font-semibold text-sm bg-ink text-cream hover:bg-ink-muted disabled:opacity-50"
                    >
                        Accept and link client
                    </button>
                    <a
                        href={route('practice.dashboard')}
                        className="px-5 py-2.5 rounded-2xl font-semibold text-sm border border-border-warm text-ink-muted hover:text-ink hover:border-ink/40"
                    >
                        Cancel
                    </a>
                </form>
            </div>
        </PracticeLayout>
    );
}
