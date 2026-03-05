import React, { useState } from 'react';
import { Form, Formik, FormikHelpers } from 'formik';
import * as Yup from 'yup';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button';
import { Dialog } from '@/components/elements/dialog';
import Field from '@/components/elements/Field';
import asDialog from '@/hoc/asDialog';
import Spinner from '@/components/elements/Spinner';
import { Placeholder, StoreFile } from '@/api/server/mcsetups/store';

interface Props {
    file: StoreFile;
    onClose(): void;
    onComplete(values: Record<string, string>): void;
}

interface FormValues {
    [key: string]: string;
}

const OnboardingModalContent = asDialog({
    title: 'Setup Configuration',
    description: 'Fill in the fields to configure your server setup',
})((props: Props) => {
    const [loading, setLoading] = useState(false);

    const initialValues: FormValues = {};
    props.file.placeholders.forEach(placeholder => {
        initialValues[placeholder.token] = '';
    });

    const validationSchema = Yup.object().shape(
        Object.fromEntries(
            props.file.placeholders.map(placeholder => [
                placeholder.token,
                Yup.string().required(`${placeholder.label} is required`),
            ])
        )
    );

    const handleSubmit = async (values: FormValues, { setSubmitting }: FormikHelpers<FormValues>) => {
        setLoading(true);
        try {
            props.onComplete(values);
        } finally {
            setLoading(false);
            setSubmitting(false);
        }
    };

    return (
        <Formik
            initialValues={initialValues}
            validationSchema={validationSchema}
            onSubmit={handleSubmit}
        >
            {({ isSubmitting, submitForm }) => (
                <Form>
                    <div css={tw`space-y-4 max-h-[60vh] overflow-y-auto pr-2`}>
                        {props.file.placeholders.map((placeholder) => (
                            <Field
                                key={placeholder.token}
                                id={placeholder.token}
                                name={placeholder.token}
                                label={placeholder.label}
                                description={placeholder.description}
                                placeholder={placeholder.example}
                            />
                        ))}
                    </div>

                    <Dialog.Footer>
                        <Button
                            type="submit"
                            onClick={submitForm}
                            disabled={loading || isSubmitting}
                        >
                            {loading || isSubmitting ? (
                                <>
                                    <Spinner size={'small'} css={tw`mr-2`} />
                                    Processing...
                                </>
                            ) : (
                                'Continue'
                            )}
                        </Button>
                    </Dialog.Footer>
                </Form>
            )}
        </Formik>
    );
});

export default ({ open, onClose, file, onComplete }: Props & { open: boolean }) => {
    if (!onClose || typeof onClose !== 'function') {
        return null;
    }
    return (
        <OnboardingModalContent
            open={open}
            onClose={onClose}
            file={file}
            onComplete={onComplete}
        />
    );
};

